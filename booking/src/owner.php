<?php
/* Sign-in to the owner's hours page. One shared password (owner, 14 Nov) for the owner, the site's admin and their
   devices (PC on Windows and Ubuntu, phones). Each browser signs in once and is remembered for a year by a random
   cookie; only its hash is stored. The page lists the devices, each can be signed out, and a new password signs
   out all of them. Wrong passwords are slowed down per visitor and overall. */
declare(strict_types=1);

const OWNER_COOKIE = 'khg_hodiny';
const OWNER_DEVICE_DAYS = 365;
const LOGIN_FAILS_VISITOR = 5;     // per visitor in 15 minutes
const LOGIN_FAILS_ALL = 30;        // everyone together in an hour (many addresses guessing)

/* "Chrome · Windows": enough to tell her devices apart in the list, nothing more is kept. */
function device_label(string $ua): string
{
    $b = match (true) {
        str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
        str_contains($ua, 'Firefox/') => 'Firefox', str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari',
        default => 'Prohlížeč',
    };
    $os = match (true) {
        str_contains($ua, 'Android') => 'Android', (bool)preg_match('/iPhone|iPad/', $ua) => 'iPhone', str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Mac OS') => 'Mac', str_contains($ua, 'Ubuntu') => 'Ubuntu', str_contains($ua, 'Linux') => 'Linux',
        default => 'neznámé zařízení',
    };
    return "$b · $os";
}

function token_hash(string $token): string { return hash_hmac('sha256', 'owner-device:' . $token, cfg()['secret']); }

/* Checks the password; on success a new device row and its cookie value. */
function owner_login(string $password, string $ua, string $who, ?int $now = null): array
{
    $now ??= time();
    $hash = cfg()['owner_password'];
    if ($hash === '') throw new ClientError('Heslo ještě není nastavené.', 503);
    $pdo = db();
    $pdo->prepare('DELETE FROM login_fails WHERE at < ?')->execute([$now - 3600]);
    $s = $pdo->prepare('SELECT COUNT(*) FROM login_fails WHERE who = ? AND at >= ?');
    $s->execute([$who, $now - 900]);
    $mine = (int)$s->fetchColumn();
    $all = (int)$pdo->query('SELECT COUNT(*) FROM login_fails')->fetchColumn();
    if ($mine >= LOGIN_FAILS_VISITOR || $all >= LOGIN_FAILS_ALL) {
        log_line('OWNER_LOGIN_LOCKED', ['who' => $who, 'mine' => $mine, 'all' => $all]);
        throw new ClientError('Příliš mnoho pokusů. Zkus to znovu za 15 minut.', 429);
    }
    if ($password === '' || strlen($password) > 200 || !password_verify($password, $hash)) {
        $pdo->prepare('INSERT INTO login_fails (who, at) VALUES (?, ?)')->execute([$who, $now]);
        log_line('OWNER_LOGIN_FAILED', ['who' => $who]);
        throw new ClientError('Špatné heslo.', 401);
    }
    $pdo->prepare('DELETE FROM login_fails WHERE who = ?')->execute([$who]);
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $id = bin2hex(random_bytes(6));
    $label = device_label($ua);
    $pdo->prepare('INSERT INTO owner_devices (id, token_hash, label, created_at, last_seen) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, token_hash($token), $label, $now, $now]);
    log_line('OWNER_LOGIN', ['device' => $id, 'label' => $label]);
    return ['id' => $id, 'token' => $token, 'label' => $label];
}

/* The signed-in device of this cookie value, or null. Seen at most once a minute, to keep writes down. */
function owner_device(?string $token, ?int $now = null): ?array
{
    $now ??= time();
    if (!$token || strlen($token) > 100) return null;
    $s = db()->prepare('SELECT * FROM owner_devices WHERE token_hash = ? AND revoked_at IS NULL AND last_seen >= ?');
    $s->execute([token_hash($token), $now - OWNER_DEVICE_DAYS * 86400]);
    $d = $s->fetch() ?: null;
    if ($d && $now - (int)$d['last_seen'] > 60) db()->prepare('UPDATE owner_devices SET last_seen = ? WHERE id = ?')->execute([$now, $d['id']]);
    return $d;
}

function owner_devices(string $current): array
{
    $rows = db()->query('SELECT id, label, created_at, last_seen FROM owner_devices WHERE revoked_at IS NULL ORDER BY last_seen DESC')->fetchAll();
    return array_map(fn($r) => ['id' => $r['id'], 'label' => $r['label'], 'since' => date('Y-m-d', (int)$r['created_at']),
        'seen' => date(DATE_ATOM, (int)$r['last_seen']), 'current' => $r['id'] === $current], $rows);
}

function owner_logout(string $id, string $by): void
{
    db()->prepare('UPDATE owner_devices SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL')->execute([time(), $id]);
    log_line('OWNER_LOGOUT', ['device' => $id, 'by' => $by]);
}

/* A year-long cookie only for the hours API, never sent to other sites or readable by scripts. */
function owner_cookie(string $value, int $days): void
{
    $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off' || !lab();
    setcookie(OWNER_COOKIE, $value, ['expires' => $days ? time() + $days * 86400 : 1, 'path' => '/api/hodiny',
        'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
}
