<?php
/* Storage: one SQLite file outside the web root. Two tables:
   holds     a slot someone is paying for right now (expires with the Stripe session)
   bookings  one row per paid Checkout Session; the payment id keeps every path (webhook, return page,
             safety net) from booking twice */
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $path = cfg()['db'];
    $new = !is_file($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 10]);
    if ($new) @chmod($path, 0600);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('CREATE TABLE IF NOT EXISTS holds (
        slot TEXT PRIMARY KEY,            -- session start, Prague time ISO
        pay_id TEXT,                      -- Checkout Session id, set once Stripe answered
        expires_at INTEGER NOT NULL)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
        pay_id TEXT PRIMARY KEY,
        slot TEXT NOT NULL,
        forma TEXT NOT NULL,
        email TEXT, phone TEXT, name TEXT,
        status TEXT NOT NULL,             -- pending | booked | failed | cancelled
        event_id TEXT, meet TEXT,
        clash INTEGER DEFAULT 0,          -- the owner had something else in the calendar meanwhile
        mail_sent INTEGER DEFAULT 0,
        refund_id TEXT, refund_status TEXT,
        hours_before REAL,
        error TEXT,
        created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)");
    // what the client wrote in "S čím přicházíte": kept here only (not in Stripe, not in the calendar event,
    // which online clients can read as guests), from the checkout until the booking takes it over
    $pdo->exec('CREATE TABLE IF NOT EXISTS notes (pay_id TEXT PRIMARY KEY, note TEXT NOT NULL, created_at INTEGER NOT NULL)');
    // e-mails that must reach someone (cancellations, alerts to her): kept until sent, the safety net retries
    $pdo->exec('CREATE TABLE IF NOT EXISTS mail_queue (name TEXT PRIMARY KEY, pay_id TEXT NOT NULL, kind TEXT NOT NULL, created_at INTEGER NOT NULL)');
    // columns added after the first version. Several requests may start at once on a fresh deploy and all see the
    // same column missing (16 Nov review: "duplicate column" errors): the check is repeated under the write lock,
    // so one request adds them and the others find them there.
    $missing = fn() => array_filter(db_columns_missing($pdo), fn($cols) => $cols !== []);
    if ($missing()) {
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            foreach ($missing() as $table => $cols) foreach ($cols as $col => $type) {
                $pdo->exec("ALTER TABLE $table ADD COLUMN $col $type");
                // existing bookings count as already announced to the owner
                if ($table === 'bookings' && $col === 'owner_mailed') $pdo->exec('UPDATE bookings SET owner_mailed = 1');
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $e) {
            $pdo->exec('ROLLBACK');
            throw $e;
        }
    }
    // small settings the cron keeps between runs (the calendar sync's last check)
    $pdo->exec('CREATE TABLE IF NOT EXISTS state (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
    // the owner's hours (src/hours.php) and the devices signed in to her hours page (src/owner.php)
    $pdo->exec('CREATE TABLE IF NOT EXISTS hours_week (forma TEXT NOT NULL, wd INTEGER NOT NULL, hours TEXT NOT NULL, PRIMARY KEY (forma, wd))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS hours_day (forma TEXT NOT NULL, day TEXT NOT NULL, hours TEXT NOT NULL, PRIMARY KEY (forma, day))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS owner_devices (id TEXT PRIMARY KEY, token_hash TEXT NOT NULL UNIQUE, label TEXT NOT NULL,
        created_at INTEGER NOT NULL, last_seen INTEGER NOT NULL, revoked_at INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS login_fails (who TEXT NOT NULL, at INTEGER NOT NULL)');
    return $pdo;
}

/* Columns added after the first version, per table, that this database does not have yet. */
function db_columns_missing(PDO $pdo): array
{
    $want = [
        // client_moves: how many times the client moved it (no limit; shown to the owner); declined_mailed: the owner was
        // told the guest declined; reminded: flags of the reminders sent, 1 = the day before, 2 = just before (a move
        // resets it); lock_until: a move is talking to Google right now (one move at a time); stale_event: a cancelled
        // booking's calendar event that Google did not delete yet (the safety net tries again)
        'bookings' => ['note' => 'TEXT', 'owner_mailed' => 'INTEGER DEFAULT 0', 'moves' => 'INTEGER DEFAULT 0', 'cancelled_by' => 'TEXT',
            'client_moves' => 'INTEGER DEFAULT 0', 'declined_mailed' => 'INTEGER DEFAULT 0', 'moved_at' => 'INTEGER',
            'lock_until' => 'INTEGER', 'stale_event' => 'TEXT', 'reminded' => 'INTEGER DEFAULT 0',
            // mail_try / owner_try: when a request or the safety net started sending the client's / her e-mail (only one
            // sends it, see mail_claim); mail_dead: the client's address refused the confirmation for good
            'mail_try' => 'INTEGER', 'owner_try' => 'INTEGER', 'mail_dead' => 'INTEGER DEFAULT 0',
            // payment_intent / amount: the Stripe payment behind the booking and what was paid (in hellers), to compare
            // all its refunds with (17 Nov review, second list); older bookings get them from Stripe on first need
            'payment_intent' => 'TEXT', 'amount' => 'INTEGER'],
        // tried_at: someone is sending this queued e-mail right now (see mail_claim)
        'mail_queue' => ['tried_at' => 'INTEGER'],
        // who: keyed hash of the visitor's IP (never the IP itself), to cap open holds per visitor; browser: keyed hash
        // of the paying browser's cookie, so its own hold does not block it (see browser_id); nonce: which request made
        // the hold, so a request only ever fills in or removes its own
        'holds' => ['who' => 'TEXT', 'browser' => 'TEXT', 'nonce' => 'TEXT'],
    ];
    $out = [];
    foreach ($want as $table => $cols) {
        $have = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        $out[$table] = array_diff_key($cols, array_flip($have));
    }
    return $out;
}

function state_get(string $k): ?string
{
    $s = db()->prepare('SELECT v FROM state WHERE k = ?');
    $s->execute([$k]);
    $v = $s->fetchColumn();
    return $v === false ? null : (string)$v;
}

function state_set(string $k, string $v): void
{
    db()->prepare('INSERT OR REPLACE INTO state (k, v) VALUES (?, ?)')->execute([$k, $v]);
}

/* BEGIN IMMEDIATE takes the write lock at once, so two requests for the same slot run one after the other. */
function db_tx(callable $fn)
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $r = $fn($pdo);
        $pdo->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function booking_get(string $payId): ?array
{
    $s = db()->prepare('SELECT * FROM bookings WHERE pay_id = ?');
    $s->execute([$payId]);
    return $s->fetch() ?: null;
}

function booking_update(string $payId, array $fields): void
{
    $fields['updated_at'] = time();
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
    db()->prepare("UPDATE bookings SET $set WHERE pay_id = :pay_id")->execute($fields + ['pay_id' => $payId]);
}
