<?php
/* Safety net, run by the hosting's Scheduled Tasks every 5 minutes:
     /usr/bin/php84 -f /home/sites/.../booking/cron.php
   Not reachable from the web (live: outside public_html). */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/src/config.php';
require __DIR__ . '/src/stripe.php';
require __DIR__ . '/src/google.php';
require __DIR__ . '/src/store.php';
require __DIR__ . '/src/mail.php';
require __DIR__ . '/src/booking.php';
require __DIR__ . '/src/slots.php';
require __DIR__ . '/src/hours.php';

// one run at a time (review 17 Nov: a slow run overlapping the next could send the same e-mails twice)
$lock = fopen(dirname(cfg()['db']) . '/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { echo "{\"skipped\":\"previous run still going\"}\n"; exit; }
echo json_encode(sweep(), JSON_UNESCAPED_SLASHES), "\n";
