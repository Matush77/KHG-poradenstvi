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

echo json_encode(sweep(), JSON_UNESCAPED_SLASHES), "\n";
