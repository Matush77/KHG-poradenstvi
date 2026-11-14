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
    // columns added after the first version; existing bookings count as already announced to the owner
    $have = array_column($pdo->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');
    // client_moves: how many times the client moved it (no limit; shown to the owner); declined_mailed: the owner was told the guest declined
    foreach (['note' => 'TEXT', 'owner_mailed' => 'INTEGER DEFAULT 0', 'moves' => 'INTEGER DEFAULT 0', 'cancelled_by' => 'TEXT',
        'client_moves' => 'INTEGER DEFAULT 0', 'declined_mailed' => 'INTEGER DEFAULT 0', 'moved_at' => 'INTEGER'] as $col => $type) {
        if (in_array($col, $have, true)) continue;
        $pdo->exec("ALTER TABLE bookings ADD COLUMN $col $type");
        if ($col === 'owner_mailed') $pdo->exec('UPDATE bookings SET owner_mailed = 1');
    }
    // holds.who: keyed hash of the visitor's IP (never the IP itself), to cap open holds per visitor;
    // bookings.reminded: flags of the reminders sent, 1 = the day before, 2 = just before (a move resets it)
    if (!in_array('who', array_column($pdo->query('PRAGMA table_info(holds)')->fetchAll(), 'name'), true)) {
        $pdo->exec('ALTER TABLE holds ADD COLUMN who TEXT');
    }
    // holds.browser: keyed hash of the paying browser's cookie, so its own hold does not block it (see browser_id)
    if (!in_array('browser', array_column($pdo->query('PRAGMA table_info(holds)')->fetchAll(), 'name'), true)) {
        $pdo->exec('ALTER TABLE holds ADD COLUMN browser TEXT');
    }
    if (!in_array('reminded', $have, true)) $pdo->exec('ALTER TABLE bookings ADD COLUMN reminded INTEGER DEFAULT 0');
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
