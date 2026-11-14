<?php
/* Free times. The owner's hours come from her hours page (src/hours.php, HOURS_SOURCE=db, the default) or,
   the earlier way, from events in two Google calendars ("Ordinace online", "Ordinace osobně"); anything busy in her own calendar or the bookings calendar, a slot someone is paying
   for right now, and Czech public holidays are taken out. One free/busy call reads all of it, so no
   permission beyond calendar.freebusy is needed. Sessions are 50 minutes, offered every hour from the
   start of each block of hours (a block 8:00–12:00 gives 8, 9, 10, 11). */
declare(strict_types=1);

const SESSION_MIN = 50;
const SLOT_STEP_MIN = 60;

/* Czech public holidays of a year as 'Y-m-d' => name (Easter computed, no calendar extension needed). */
function cz_holiday_names(int $y): array
{
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31); $day = (($h + $l - 7 * $m + 114) % 31) + 1;
    $easter = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $month, $day));
    $out = [];
    foreach (['01-01' => 'Nový rok', '05-01' => 'Svátek práce', '05-08' => 'Den vítězství', '07-05' => 'Cyril a Metoděj',
        '07-06' => 'Jan Hus', '09-28' => 'Den české státnosti', '10-28' => 'Vznik Československa', '11-17' => 'Den boje za svobodu',
        '12-24' => 'Štědrý den', '12-25' => '1. svátek vánoční', '12-26' => '2. svátek vánoční'] as $md => $name) $out["$y-$md"] = $name;
    $out[$easter->modify('-2 days')->format('Y-m-d')] = 'Velký pátek';
    $out[$easter->modify('+1 day')->format('Y-m-d')] = 'Velikonoční pondělí';
    ksort($out);
    return $out;
}

function cz_holidays(int $y): array { return array_map(fn() => true, cz_holiday_names($y)); }

/* Pure part, tested offline. $open: [forma => [[start ts, end ts], ...]], $busy: [[start ts, end ts], ...].
   Returns [forma => ['Y-m-d' => ['9:00', ...]]] for starts in [$from, $to]. */
function compute_slots(array $open, array $busy, DateTimeImmutable $from, DateTimeImmutable $to, bool $skipHolidays): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $holidays = [];
    if ($skipHolidays) for ($y = (int)$from->format('Y'); $y <= (int)$to->format('Y'); $y++) $holidays += cz_holidays($y);
    $out = [];
    foreach (FORMATS as $forma) {
        $days = [];
        foreach ($open[$forma] ?? [] as [$ws, $we]) {
            for ($t = $ws; $t + SESSION_MIN * 60 <= $we; $t += SLOT_STEP_MIN * 60) {
                if ($t < $from->getTimestamp() || $t > $to->getTimestamp()) continue;
                $start = (new DateTimeImmutable('@' . $t))->setTimezone($tz);
                $hour = (int)$start->format('G');
                if ($hour < 6 || $hour > 20) continue;                  // an all-day event in an hours calendar is not "open at 3:00"
                if (isset($holidays[$start->format('Y-m-d')])) continue;
                $end = $t + SESSION_MIN * 60;
                foreach ($busy as [$bs, $be]) if ($bs < $end && $be > $t) continue 2;
                $days[$start->format('Y-m-d')][$t] = $start->format('G:i');
            }
        }
        ksort($days);
        $out[$forma] = array_map(function ($d) { ksort($d); return array_values($d); }, $days);
    }
    return $out;
}

/* The booking window: from now + minimum notice to the end of the last bookable day. */
function slot_window(?DateTimeImmutable $now = null): array
{
    $c = cfg();
    $now = ($now ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('Europe/Prague'));
    return [$now->modify('+' . $c['min_notice_hours'] . ' hours'), $now->setTime(23, 59, 59)->modify('+' . $c['book_ahead_days'] . ' days')];
}

/* Free times read from Google now. $day: only that day (checkout re-check). $withTaken: also leave out slots
   held or booked in our database (the page); the checkout checks those itself, inside its transaction. */
function free_slots(?DateTimeImmutable $day = null, bool $withTaken = true): array
{
    $c = cfg();
    $fromDb = $c['hours_source'] === 'db';   // the hours page (src/hours.php); 'google': the two hours calendars
    if (!$fromDb) foreach (FORMATS as $f) if ($c['hours'][$f] === '') throw new RuntimeException("Hours calendar for $f is not configured.");
    [$from, $to] = slot_window();
    $qFrom = $day ? max($from, $day->setTime(0, 0)) : $from;
    $qTo = $day ? min($to, $day->setTime(23, 59, 59)) : $to;
    if ($qFrom > $qTo) return array_fill_keys(FORMATS, []);
    $busyIds = array_merge($c['busy_calendars'], [$c['calendar']]);
    $fb = gcal_freebusy(array_merge($fromDb ? [] : array_values($c['hours']), $busyIds), $qFrom->setTime(0, 0), $qTo);
    $open = $fromDb ? hours_open($qFrom, $qTo) : [];
    if (!$fromDb) foreach (FORMATS as $f) $open[$f] = $fb[$c['hours'][$f]];
    $busy = [];
    foreach (array_unique($busyIds) as $id) {
        // a calendar can be both hours and busy only by mistake; never let hours block themselves
        if (in_array($id, $c['hours'], true)) continue;
        array_push($busy, ...$fb[$id]);
    }
    if ($withTaken) {
        $rows = db()->query("SELECT slot FROM holds WHERE expires_at >= " . time() .
            " UNION SELECT slot FROM bookings WHERE status IN ('pending','booked','failed')")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $s) { $t = strtotime($s); $busy[] = [$t, $t + SESSION_MIN * 60]; }
    }
    // from the database a holiday is already closed unless she opened hours on it herself
    return compute_slots($open, $busy, $qFrom, $qTo, $fromDb ? false : $c['skip_public_holidays']);
}
