<?php
/* Local testing only: `php -S 127.0.0.1:8820 -t . booking/router.php` from the repo root (live: .htaccess sends /api/ here).
   /api/* goes to the booking API, everything else is served as a static file (like the live site). */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with((string)$path, '/api/')) {
    require __DIR__ . '/api.php';
    return true;
}
return false;
