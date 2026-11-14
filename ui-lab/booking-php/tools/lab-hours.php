<?php
/* Lab only: creates two demo hours calendars ("KHG test · Ordinace online / osobně") with weekly repeating
   hours, the way the owner would set up her real "Ordinace online" and "Ordinace osobně" calendars.
   The calendars belong to this app (scope calendar.app.created), so nothing of the owner's is touched.
   Saves their ids to ~/.config/khg/google-hours-calendars.json. Run once: php ui-lab/booking-php/tools/lab-hours.php */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/google.php';

if (!lab()) exit("Lab only.\n");
$file = cfg()['dir'] . '/google-hours-calendars.json';
if (is_file($file)) exit("Already set up: $file\n");

// Roughly the hours behind the live booking pages on 13 Nov (owner sets the real ones herself).
$hours = [
    'online' => ['name' => 'KHG test · Ordinace online', 'blocks' => [
        ['MO,TU', '08:00', '12:00'], ['MO,TU', '13:00', '15:00'], ['WE', '14:00', '16:00'], ['TH,FR', '09:00', '12:00'], ['TH,FR', '14:00', '16:00']]],
    'osobne' => ['name' => 'KHG test · Ordinace osobně', 'blocks' => [
        ['MO,TU', '08:00', '12:00'], ['MO,TU', '13:00', '15:00'], ['WE', '10:00', '13:00'], ['FR', '09:00', '12:00']]],
];
$firstMonday = new DateTimeImmutable('monday this week', new DateTimeZone('Europe/Prague'));
$byday = ['MO' => 0, 'TU' => 1, 'WE' => 2, 'TH' => 3, 'FR' => 4];
$out = [];
foreach ($hours as $forma => $h) {
    $cal = gcal('POST', 'calendars', ['summary' => $h['name'], 'timeZone' => 'Europe/Prague']);
    $out[$forma] = $cal['id'];
    foreach ($h['blocks'] as [$days, $from, $to]) {
        $day = $firstMonday->modify('+' . $byday[explode(',', $days)[0]] . ' days')->format('Y-m-d');
        gcal('POST', 'calendars/' . rawurlencode($cal['id']) . '/events', [
            'summary' => 'Ordinace', 'transparency' => 'opaque',
            'start' => ['dateTime' => "{$day}T$from:00", 'timeZone' => 'Europe/Prague'],
            'end' => ['dateTime' => "{$day}T$to:00", 'timeZone' => 'Europe/Prague'],
            'recurrence' => ["RRULE:FREQ=WEEKLY;BYDAY=$days"],
        ], ['sendUpdates' => 'none']);
    }
    echo "$forma: {$h['name']} (" . count($h['blocks']) . " weekly blocks)\n";
}
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT) . "\n");
chmod($file, 0600);
echo "Saved $file\n";
