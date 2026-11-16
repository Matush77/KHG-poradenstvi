<?php
/* Lab only: `php -S 127.0.0.1:8820 -t . ui-lab/booking-php/router.php` from the repo root.
   /api/* goes to the booking API, everything else is served as a static file (like the live site). */
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }   // only PHP's own test server
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with((string)$path, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}
return false;
