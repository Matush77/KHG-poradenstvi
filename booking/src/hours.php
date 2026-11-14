<?php
/* The owner's working hours, kept in our own database and edited only on her hours page (rezervace/hodiny.html).
   Owner, 14 Nov: editing them must be as easy as Google's booking page or easier; Google's appointment hours can't be
   read through the API, and writing repeating Google events with exceptions from a page would be fragile.

   hours_week  forma, weekday (0 = Sunday) -> the hours open every week ([9, 10, 14] = sessions at 9:00, 10:00, 14:00)
   hours_day   forma, date -> that day's own hours (an empty list = closed that day); no row = the regular week
   A public holiday without its own row is closed (setting skip_public_holidays); she can still open hours on one.
   Every save bumps hours_version, so two devices saving at once can't overwrite each other unseen.
   Her own Google calendar still blocks time (doctor, holiday): free_slots reads it as busy, as before. */
declare(strict_types=1);

const OWNER_FIRST_HOUR = 8;    // the hours page offers 8:00 ... 17:00 (owner, 14 Nov: nothing after 18:00)
const OWNER_LAST_HOUR = 17;

function hours_clean($list): array
{
    if (!is_array($list)) throw new ClientError('Neplatné hodiny.', 400);
    $out = [];
    foreach ($list as $h) {
        if (!is_int($h) || $h < OWNER_FIRST_HOUR || $h > OWNER_LAST_HOUR) throw new ClientError('Neplatná hodina.', 400);
        $out[$h] = $h;
    }
    sort($out);
    return array_values($out);
}

function hours_week(): array
{
    $out = array_fill_keys(FORMATS, []);
    foreach (db()->query('SELECT forma, wd, hours FROM hours_week') as $r) $out[$r['forma']][(int)$r['wd']] = json_decode($r['hours'], true);
    return $out;
}

/* Own hours of the days in [from, to] (Y-m-d). */
function hours_days(string $from, string $to): array
{
    $out = array_fill_keys(FORMATS, []);
    $s = db()->prepare('SELECT forma, day, hours FROM hours_day WHERE day BETWEEN ? AND ? ORDER BY day');
    $s->execute([$from, $to]);
    foreach ($s as $r) $out[$r['forma']][$r['day']] = json_decode($r['hours'], true);
    return $out;
}

function hours_version(): int { return (int)(state_get('hours_version') ?? 0); }

/* Holidays of the years in [from, to] that close a day by default: 'Y-m-d' => name. */
function hours_holidays(DateTimeImmutable $from, DateTimeImmutable $to): array
{
    if (!cfg()['skip_public_holidays']) return [];
    $out = [];
    for ($y = (int)$from->format('Y'); $y <= (int)$to->format('Y'); $y++) $out += cz_holiday_names($y);
    return $out;
}

/* Open time per format as hour-long blocks, the shape compute_slots reads: [forma => [[start ts, end ts], ...]]. */
function hours_open(DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $from = $from->setTimezone($tz)->setTime(0, 0);
    $week = hours_week();
    $days = hours_days($from->format('Y-m-d'), $to->format('Y-m-d'));
    $hol = hours_holidays($from, $to);
    $open = array_fill_keys(FORMATS, []);
    for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
        $k = $d->format('Y-m-d');
        foreach (FORMATS as $f) {
            $list = $days[$f][$k] ?? (isset($hol[$k]) ? [] : ($week[$f][(int)$d->format('w')] ?? []));
            foreach ($list as $h) {
                $t = $d->setTime($h, 0)->getTimestamp();   // setTime in Prague: right across the clock change
                $open[$f][] = [$t, $t + 3600];
            }
        }
    }
    return $open;
}

/* Saves the page's draft. $week: [forma => [wd => [h...]]] (whole week of each format sent);
   $days: [forma => ['Y-m-d' => [h...] | null]] (null = back to the regular week). Returns the new version. */
function hours_save(array $week, array $days, int $base, string $who, ?DateTimeImmutable $now = null): int
{
    $now = ($now ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('Europe/Prague'));
    $first = $now->format('Y-m-d');
    $last = $now->modify('+' . (cfg()['book_ahead_days'] + 31) . ' days')->format('Y-m-d');
    $rowsW = [];
    foreach ($week as $f => $wds) {
        if (!in_array($f, FORMATS, true) || !is_array($wds)) throw new ClientError('Neplatná forma.', 400);
        foreach ($wds as $wd => $list) {
            if (!preg_match('/^[0-6]$/', (string)$wd)) throw new ClientError('Neplatný den.', 400);
            $rowsW[] = [$f, (int)$wd, hours_clean($list)];
        }
    }
    $rowsD = [];
    foreach ($days as $f => $ks) {
        if (!in_array($f, FORMATS, true) || !is_array($ks)) throw new ClientError('Neplatná forma.', 400);
        foreach ($ks as $k => $list) {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$k);
            if (!$d || $d->format('Y-m-d') !== $k) throw new ClientError('Neplatné datum.', 400);
            if ($k < $first || $k > $last) throw new ClientError('Den mimo rozsah.', 400);
            $rowsD[] = [$f, $k, $list === null ? null : hours_clean($list)];
        }
    }
    if (count($rowsD) > 1000) throw new ClientError('Příliš mnoho změn najednou.', 400);
    return db_tx(function (PDO $pdo) use ($rowsW, $rowsD, $base, $who, $first) {
        $v = hours_version();
        if ($base !== $v) throw new ClientError('Mezitím hodiny změnil někdo jiný. Načti stránku znovu.', 409);
        $w = $pdo->prepare('INSERT OR REPLACE INTO hours_week (forma, wd, hours) VALUES (?, ?, ?)');
        foreach ($rowsW as [$f, $wd, $l]) $w->execute([$f, $wd, json_encode($l)]);
        $set = $pdo->prepare('INSERT OR REPLACE INTO hours_day (forma, day, hours) VALUES (?, ?, ?)');
        $del = $pdo->prepare('DELETE FROM hours_day WHERE forma = ? AND day = ?');
        foreach ($rowsD as [$f, $k, $l]) $l === null ? $del->execute([$f, $k]) : $set->execute([$f, $k, json_encode($l)]);
        $pdo->prepare('DELETE FROM hours_day WHERE day < ?')->execute([$first]);   // days gone by
        state_set('hours_version', (string)($v + 1));
        log_line('HOURS_SAVED', ['by' => $who, 'week' => count($rowsW), 'days' => array_map(fn($r) => $r[0] . ' ' . $r[1] . ' ' . ($r[2] === null ? 'týden' : implode(',', $r[2])), $rowsD)]);
        return $v + 1;
    });
}

/* Everything the hours page shows: hours, holidays, the bookings in the window (with her link to each). */
function hours_view(?DateTimeImmutable $now = null): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $now = ($now ?? new DateTimeImmutable())->setTimezone($tz);
    $to = $now->setTime(0, 0)->modify('+' . cfg()['book_ahead_days'] . ' days');
    $booked = [];
    $s = db()->prepare("SELECT pay_id, slot, forma, name FROM bookings WHERE status IN ('pending','booked') AND slot >= ? ORDER BY slot");
    $s->execute([$now->setTime(0, 0)->format(DATE_ATOM)]);
    foreach ($s as $r) {
        $t = prague($r['slot']);
        if ($t > $to->setTime(23, 59)) continue;
        $booked[$t->format('Y-m-d')][(int)$t->format('G')] = ['n' => $r['name'] ?: 'Bez jména', 'f' => $r['forma'],
            'cas' => $t->format('G:i'), 'url' => substr(owner_url($r['pay_id']), strlen(site()))];
    }
    return ['version' => hours_version(), 'today' => $now->format('Y-m-d'), 'now' => $now->format(DATE_ATOM), 'to' => $to->format('Y-m-d'),
        'first_hour' => OWNER_FIRST_HOUR, 'last_hour' => OWNER_LAST_HOUR, 'min_notice_hours' => cfg()['min_notice_hours'],
        // objects, not lists, so the page can add keys and send them back as they are
        'week' => array_map(fn($x) => (object)$x, hours_week()),
        'days' => array_map(fn($x) => (object)$x, hours_days($now->format('Y-m-d'), $to->format('Y-m-d'))),
        'holidays' => (object)hours_holidays($now, $to), 'booked' => (object)$booked];
}
