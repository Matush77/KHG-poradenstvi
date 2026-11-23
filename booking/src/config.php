<?php
/* Booking back end: configuration.
   Everything secret lives outside the web root, in KHG_CONFIG_DIR (lab: ~/.config/khg, chmod 600 files).
   Written for PHP 8.4 (the hosting); only curl, openssl, pdo_sqlite and hash are needed (no mbstring, no intl). */
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');

/* Where the secrets and the database live: next to the site folder, never inside it. The hosting runs two copies
   from one account, so each gets its own: public_html (khgporadenstvi.cz) -> ~/.config/khg, test_html
   (test.khgporadenstvi.cz) -> ~/.config/khg-test, and test bookings never mix with real ones. Lab and local runs:
   ~/.config/khg, or KHG_CONFIG_DIR. */
function config_dir(?string $site = null): string
{
    if ($d = getenv('KHG_CONFIG_DIR')) return $d;
    $site ??= dirname(__DIR__, 2);                 // the folder that holds booking/
    $name = basename($site);
    if ($name === 'public_html') return dirname($site) . '/.config/khg';
    if (str_starts_with($name, 'test')) return dirname($site) . '/.config/khg-test';
    return (getenv('HOME') ?: dirname($site)) . '/.config/khg';
}

/* Which copy this is (khg = the live site or the lab, khg-test = the test subdomain), from its config folder.
   Written into each payment, so a copy books only its own. */
function site_copy(): string { return basename(rtrim(cfg()['dir'], '/')); }

function cfg(): array
{
    static $c = null;
    if ($c !== null) return $c;
    $dir = config_dir();
    $env = [];
    foreach (['stripe-test.env', 'google-oauth.env', 'booking.env', 'smtp.env'] as $f) {
        if (!is_file("$dir/$f")) continue;
        foreach (file("$dir/$f", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
    $json = fn(string $f) => is_file("$dir/$f") ? (json_decode((string)file_get_contents("$dir/$f"), true) ?: []) : [];
    $c = [
        'dir' => $dir,
        'mode' => $env['BOOKING_MODE'] ?? 'lab',                  // lab | live
        'stripe_sk' => $env['STRIPE_SECRET_KEY'] ?? '',
        'stripe_pk' => $env['STRIPE_PUBLISHABLE_KEY'] ?? '',
        'stripe_whsec' => $env['STRIPE_WEBHOOK_SECRET'] ?? '',
        'google_id' => $env['GOOGLE_CLIENT_ID'] ?? '',
        'google_secret' => $env['GOOGLE_CLIENT_SECRET'] ?? '',
        'google_refresh' => $json('google-token.json')['refresh_token'] ?? '',
        // the calendar the bookings are written to; its own events are also read as busy
        'calendar' => $env['BOOKING_CALENDAR'] ?? ($json('google-test-calendar.json')['id'] ?? ''),
        // the owner's calendars read as busy/free only
        'busy_calendars' => array_values(array_filter(explode(',', $env['BUSY_CALENDARS'] ?? 'primary'))),
        // where her working hours come from: 'db' = her hours page (owner, 14 Nov), 'google' = the calendars below
        'hours_source' => ($env['HOURS_SOURCE'] ?? 'db') === 'google' ? 'google' : 'db',
        // the shared password of the hours page, as a password_hash() (tools/owner-password.php sets it)
        'owner_password' => is_file("$dir/owner-password") ? trim((string)file_get_contents("$dir/owner-password")) : '',
        // HOURS_SOURCE=google only: the owner's working hours per format, every (busy) event in these calendars is open time
        'hours' => [
            'online' => $env['HOURS_ONLINE'] ?? ($json('google-hours-calendars.json')['online'] ?? ''),
            'osobne' => $env['HOURS_OSOBNE'] ?? ($json('google-hours-calendars.json')['osobne'] ?? ''),
        ],
        'min_notice_hours' => (int)($env['MIN_NOTICE_HOURS'] ?? 4),    // nothing bookable sooner; the hours page overrides both
        'book_ahead_days' => (int)($env['BOOK_AHEAD_DAYS'] ?? 70),     // owner, 13 Nov
        'skip_public_holidays' => ($env['SKIP_PUBLIC_HOLIDAYS'] ?? '1') === '1',
        'secret' => is_file("$dir/booking-secret") ? trim((string)file_get_contents("$dir/booking-secret")) : '',
        'db' => $env['BOOKING_DB'] ?? "$dir/booking.sqlite",
        'outbox' => $env['MAIL_OUTBOX'] ?? "$dir/outbox-php",
        'site' => $env['SITE_URL'] ?? '',                         // '' in the lab: taken from the request
        'booking_page' => $env['BOOKING_PAGE'] ?? '/rezervace.html',
        'cancel_page' => $env['CANCEL_PAGE'] ?? '/rezervace/zruseni.html',
        'owner_page' => $env['OWNER_PAGE'] ?? '/rezervace/sprava.html',   // the owner's links in "Nová rezervace"
        // sending: Google Workspace's SMTP relay (since 17 Nov; before: the hosting's mailbox, smtp.stackmail.com),
        // signed in as her account, sent from its alias rezervace@; the app password only in smtp.env (chmod 600)
        'smtp' => ['host' => $env['SMTP_HOST'] ?? 'smtp-relay.gmail.com', 'port' => (int)($env['SMTP_PORT'] ?? 587),
            'user' => $env['SMTP_USER'] ?? 'kristyna@khgporadenstvi.cz', 'pass' => $env['SMTP_PASS'] ?? ''],
        // the cron's heartbeat (Healthchecks.io ping URL): it e-mails the owner when the pings stop. '' = no pings
        'heartbeat' => preg_match('#^https://[^\s]+$#', $env['HEARTBEAT_URL'] ?? '') ? rtrim($env['HEARTBEAT_URL'], '/') : '',
        // lab only: real e-mails go just to these addresses; the owner's e-mails are redirected to LAB_OWNER_TO
        'lab_mail_to' => array_values(array_filter(array_map('strtolower', array_map('trim', explode(',', $env['LAB_MAIL_TO'] ?? ''))))),
        'lab_owner_to' => strtolower(trim($env['LAB_OWNER_TO'] ?? '')),
        'price' => 110000,                                        // 1 100 Kč in haléře; never from the browser
        'free_cancel_hours' => 36,                                // lab tests may change it, see below
        'hold_minutes' => 30,                                     // Stripe's shortest Checkout expiry
        'holds_per_visitor' => 3,                                 // open payments at once from one IP; a 4th frees the oldest
        'remind_day_hours' => 24,                                 // reminder e-mails, like Google's booking page sends today:
        'remind_soon_minutes' => 10,                              // a day before and just before (owner, 14 Nov)
    ];
    if ($c['mode'] !== 'live' && isset($env['LAB_FREE_CANCEL_HOURS'])) $c['free_cancel_hours'] = (int)$env['LAB_FREE_CANCEL_HOURS'];
    if ($c['mode'] !== 'live' && !preg_match('/^(sk|rk)_test_/', $c['stripe_sk'])) {
        throw new RuntimeException('Lab mode refuses Stripe keys that are not test keys.');
    }
    // and the other way round (17 Nov review): the live site with test keys would book clients who paid with a test card
    if ($c['mode'] === 'live' && !preg_match('/^(sk|rk)_live_/', $c['stripe_sk'])) {
        throw new RuntimeException('Live mode refuses Stripe keys that are not live keys.');
    }
    // 16 Nov test run: without it every e-mail link would point at http://127.0.0.1:8820
    if ($c['mode'] === 'live' && !preg_match('#^https://[^/]+#', $c['site'])) {
        throw new RuntimeException('Live mode needs SITE_URL=https://… in booking.env.');
    }
    if (strlen($c['secret']) < 32) throw new RuntimeException('booking-secret missing or too short.');
    return $c;
}

function lab(): bool
{
    return cfg()['mode'] !== 'live';
}

/* Whether our cookies get the Secure flag: on HTTPS, and always live (even if a proxy hides HTTPS from PHP). */
function secure_cookies(): bool
{
    return ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off' || !lab();
}

function site(): string
{
    if (cfg()['site'] !== '') return rtrim(cfg()['site'], '/');
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8820';
    return 'http://' . (preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/', $host) ? $host : '127.0.0.1:8820');
}

function log_line(string $what, array $data = []): void
{
    $line = date('c') . ' ' . $what . ($data ? ' ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') . "\n";
    file_put_contents(cfg()['dir'] . '/booking.log', $line, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli-server' || PHP_SAPI === 'cli') file_put_contents('php://stderr', $line);
}

/* Drops the log's lines older than $keepDays (the privacy page's promise covers the log too, not only the database).
   Lines start with date('c') in Prague time, so they compare as text; a line without a date belongs to the one above.
   Under the same lock log_line() appends with, so no line written meanwhile is lost. Returns how many lines went. */
function log_trim(int $keepDays, ?int $now = null): int
{
    $path = cfg()['dir'] . '/booking.log';
    if (!is_file($path) || !($f = fopen($path, 'r+'))) return 0;
    try {
        if (!flock($f, LOCK_EX)) return 0;
        $cut = date('c', ($now ?? time()) - $keepDays * 86400);
        $keep = '';
        $dropped = 0;
        $old = false;
        while (($line = fgets($f)) !== false) {
            if (preg_match('/^\d{4}-\d\d-\d\dT/', $line)) $old = strcmp(substr($line, 0, 25), $cut) < 0;
            if ($old) $dropped++; else $keep .= $line;
        }
        if ($dropped) {
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, $keep);
            fflush($f);
        }
        return $dropped;
    } finally {
        flock($f, LOCK_UN);
        fclose($f);
    }
}

/* One small HTTP client for Stripe and Google. Returns [status, decoded body]. */
function http(string $method, string $url, array $headers = [], ?string $body = null): array
{
    // tests only (booking/tests/run.php, command line): Stripe and Google answers made up on purpose
    if (PHP_SAPI === 'cli' && isset($GLOBALS['HTTP_FAKE'])) return ($GLOBALS['HTTP_FAKE'])($method, $url, $body, $headers);
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    if ($raw === false) throw new RuntimeException("HTTP $method $url failed: $err");
    return [$code, $raw === '' ? [] : (json_decode((string)$raw, true) ?? ['raw' => $raw])];
}
