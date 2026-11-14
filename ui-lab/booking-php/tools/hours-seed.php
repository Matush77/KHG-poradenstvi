<?php
/* Lab only: fills the regular week of the hours page with the hours of the old lab calendars (tools/lab-hours.php),
   when no week is set yet. Live starts empty: the owner sets her week on the page. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/booking.php';
require __DIR__ . '/../src/slots.php';
require __DIR__ . '/../src/hours.php';

if (!lab()) exit("Lab only.\n");
if ((int)db()->query('SELECT COUNT(*) FROM hours_week')->fetchColumn()) exit("The week is already set.\n");
$week = [   // weekday (1 = Monday) => hours
    'online' => [1 => [8, 9, 10, 11, 13, 14], 2 => [8, 9, 10, 11, 13, 14], 3 => [14, 15], 4 => [9, 10, 11, 14, 15], 5 => [9, 10, 11, 14, 15]],
    'osobne' => [1 => [8, 9, 10, 11, 13, 14], 2 => [8, 9, 10, 11, 13, 14], 3 => [10, 11, 12], 5 => [9, 10, 11]],
];
echo 'version ' . hours_save($week, [], hours_version(), 'hours-seed') . "\n";
