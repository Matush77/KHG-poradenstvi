<?php
/* Booking API. Lab: served by router.php under /api/; live: the same file behind the site's .htaccess.
     GET  /api/config                 Stripe publishable key
     GET  /api/slots                  free times {from, to, online: {Y-m-d: [9:00, ...]}, osobne: {...}}
     POST /api/checkout               {f, date, time, name, email, phone} -> {url} (slot held for 30 min)
     GET  /api/checkout?id=cs_...     return page after Stripe: books if paid (the webhook usually did already)
     POST /api/webhook                Stripe events (signed); books on payment, frees the hold on expiry
     GET  /api/booking?b=&t=          the cancel page's data (signed link from the e-mail)
     POST /api/cancel {b, t}          cancel, refund when 36+ hours before
     GET  /api/terminy?b=&t=          free times of the booking's format, for the client's move
     POST /api/presunout {b, t, date, time}   the client's move (once, 36+ hours before)
     GET  /api/email?b=&t=&part=      lab only: preview of the confirmation e-mail (kind=owner: the owner's)
   The owner's links (from "Nová rezervace", signed with her own key):
     GET  /api/sprava?b=&t=           the booking with the client's details
     GET  /api/sprava/terminy?b=&t=   free times of the booking's format, to move it
     POST /api/sprava/presunout {b, t, date, time}
     POST /api/sprava/zrusit {b, t}   cancel with a full refund (or refund a late client cancellation)
   The owner's hours page (shared password, then a year-long cookie per device, src/owner.php):
     GET  /api/hodiny                 hours, holidays, bookings, signed-in devices (401 = sign in)
     POST /api/hodiny/prihlasit {heslo}
     POST /api/hodiny/ulozit {version, week, days}   saves the page's draft (409 when another device saved meanwhile)
     POST /api/hodiny/odhlasit {id}   signs a device out (no id: this one) */
declare(strict_types=1);

require __DIR__ . '/src/config.php';
require __DIR__ . '/src/stripe.php';
require __DIR__ . '/src/google.php';
require __DIR__ . '/src/store.php';
require __DIR__ . '/src/mail.php';
require __DIR__ . '/src/booking.php';
require __DIR__ . '/src/slots.php';
require __DIR__ . '/src/hours.php';
require __DIR__ . '/src/owner.php';

function out(int $code, array $body): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function body_json(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 20000);
    return is_array($j = json_decode((string)$raw, true)) ? $j : [];
}

/* The signed link from the e-mail, or 403. */
function owner_signed(string $b, string $t): string
{
    if (!owner_token_ok($b, $t)) throw new ClientError('Odkaz není platný.', 403);
    return $b;
}

function signed(string $b, string $t): string
{
    if (!token_ok($b, $t)) throw new ClientError('Odkaz není platný. Nejspíš nebyl zkopírován celý.', 403);
    return $b;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$route = preg_replace('#^.*/api/#', '', (string)$path);

try {
    if ($method === 'GET' && $route === 'config') out(200, ['pk' => cfg()['stripe_pk']]);

    if ($method === 'GET' && $route === 'slots') {
        [$from, $to] = slot_window();
        out(200, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')] + free_slots());
    }

    if ($method === 'POST' && $route === 'checkout') out(200, ['url' => create_checkout(body_json())]);

    if ($method === 'GET' && $route === 'checkout') {
        $id = (string)($_GET['id'] ?? '');
        if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $id)) throw new ClientError('Neplatná platba.', 400);
        [$code, $s] = stripe('GET', 'checkout/sessions/' . $id);
        if ($code !== 200) throw new ClientError('Platbu se nepodařilo ověřit.', 502);
        $paid = ($s['payment_status'] ?? '') === 'paid';
        $b = $paid ? book_paid($s) : null;
        out(200, ['status' => $s['status'] ?? null, 'paid' => $paid, 'meta' => $s['metadata'] ?? [], 'id' => $s['id'],
            'email' => $b['email'] ?? null,                                                   // also the one typed on Stripe's page
            'meet' => lab() ? ($b['meet'] ?? null) : null,                                   // live: the link goes only by e-mail
            'cancelled' => ($b['status'] ?? '') === 'cancelled',
            'cancel' => $paid && lab() ? substr(cancel_url($s['id']), strlen(site())) : null]);  // live: only in the e-mail
    }

    if ($method === 'POST' && $route === 'webhook') {
        $payload = (string)file_get_contents('php://input', false, null, 0, 200000);
        if (!stripe_signature_ok($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', cfg()['stripe_whsec'])) {
            log_line('WEBHOOK_BAD_SIGNATURE');
            out(400, ['error' => 'bad signature']);
        }
        $ev = json_decode($payload, true) ?: [];
        $id = (string)($ev['data']['object']['id'] ?? '');
        $type = (string)($ev['type'] ?? '');
        if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && str_starts_with($id, 'cs_')) {
            [$code, $s] = stripe('GET', 'checkout/sessions/' . $id);      // trust Stripe's API, not the body
            if ($code !== 200) out(500, ['error' => 'stripe']);           // 5xx: Stripe retries later
            $b = book_paid($s);
            log_line('WEBHOOK', ['type' => $type, 'pay' => $id, 'status' => $b['status'] ?? 'unpaid']);
        } elseif ($type === 'checkout.session.expired' && $id !== '') {
            db()->prepare('DELETE FROM holds WHERE pay_id = ?')->execute([$id]);
            log_line('WEBHOOK', ['type' => $type, 'pay' => $id]);
        }
        out(200, ['received' => true]);
    }

    if ($method === 'GET' && in_array($route, ['booking', 'email'], true)) {
        $payId = signed((string)($_GET['b'] ?? ''), (string)($_GET['t'] ?? ''));
        $b = booking_get($payId);
        if (!$b) {   // the webhook may not have arrived yet: ask Stripe
            [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($payId));
            $b = $code === 200 ? book_paid($s) : null;
        }
        if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
        if ($route === 'booking') out(200, booking_view($b));
        if (!lab()) out(404, ['error' => 'not found']);
        $m = ($_GET['kind'] ?? '') === 'owner' ? mail_owner_new($b, prague($b['slot']), owner_url($payId))
            : mail_build($b, prague($b['slot']), cancel_url($payId));
        $part = (string)($_GET['part'] ?? 'html');
        header('Cache-Control: no-store');
        header('Content-Type: ' . ($part === 'ics' ? 'text/calendar' : ($part === 'txt' ? 'text/plain' : 'text/html')) . '; charset=utf-8');
        // the brand image travels inside the real e-mail (cid); the browser preview gets it inline
        $html = str_replace(['cid:' . BRAND_CID, MAIL_FONTS . 'Fraunces/', MAIL_FONTS],
            ['data:image/png;base64,' . base64_encode(brand_png()), '/fonts/Fraunces/', '/fonts/'], $m['html']);
        echo $part === 'ics' ? (string)$m['ics'] : ($part === 'txt' ? $m['text'] : $html);
        exit;
    }

    if ($method === 'POST' && $route === 'cancel') {
        $j = body_json();
        $b = cancel_booking(signed((string)($j['b'] ?? ''), (string)($j['t'] ?? '')));
        out(200, ['cancelled' => true, 'refund' => $b['refund_id'] ? ['id' => $b['refund_id'], 'status' => $b['refund_status']] : null,
            'hours_before' => $b['hours_before']]);
    }

    if ($method === 'GET' && $route === 'terminy') {
        $b = booking_get(signed((string)($_GET['b'] ?? ''), (string)($_GET['t'] ?? ''))) ?? throw new ClientError('Rezervace nenalezena.', 404);
        if (!booking_view($b)['can_move']) throw new ClientError('Tuhle rezervaci už přesunout nejde.', 409);
        [$from, $to] = slot_window();
        out(200, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'slots' => (object)(free_slots()[$b['forma']] ?? [])]);
    }

    if ($method === 'POST' && $route === 'presunout') {
        $j = body_json();
        $b = client_move(signed((string)($j['b'] ?? ''), (string)($j['t'] ?? '')), (string)($j['date'] ?? ''), (string)($j['time'] ?? ''));
        out(200, booking_view($b));
    }

    if (str_starts_with($route, 'sprava')) {
        header('Referrer-Policy: no-referrer');
        $j = $method === 'POST' ? body_json() : $_GET;
        $payId = owner_signed((string)($j['b'] ?? ''), (string)($j['t'] ?? ''));
        if ($method === 'GET' && $route === 'sprava') {
            $b = booking_get($payId) ?? throw new ClientError('Rezervace nenalezena.', 404);
            out(200, owner_view($b));
        }
        if ($method === 'GET' && $route === 'sprava/terminy') {
            $b = booking_get($payId) ?? throw new ClientError('Rezervace nenalezena.', 404);
            [$from, $to] = slot_window();
            out(200, ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'slots' => (object)(free_slots()[$b['forma']] ?? [])]);
        }
        if ($method === 'POST' && $route === 'sprava/presunout') {
            out(200, owner_view(owner_move($payId, (string)($j['date'] ?? ''), (string)($j['time'] ?? ''))));
        }
        if ($method === 'POST' && $route === 'sprava/zrusit') out(200, owner_view(owner_cancel($payId)));
    }

    if ($route === 'hodiny' || str_starts_with($route, 'hodiny/')) {
        header('Referrer-Policy: no-referrer');
        if ($method === 'POST') {
            // a form on another site can't post here: the cookie is SameSite=Strict, and the origin must be ours
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
                throw new ClientError('Neplatný požadavek.', 403);
            }
            if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) throw new ClientError('Neplatný požadavek.', 415);
        }
        $j = $method === 'POST' ? body_json() : [];
        if ($method === 'POST' && $route === 'hodiny/prihlasit') {
            $d = owner_login((string)($j['heslo'] ?? ''), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''), visitor());
            owner_cookie($d['token'], OWNER_DEVICE_DAYS);
            out(200, ['ok' => true]);
        }
        $dev = owner_device($_COOKIE[OWNER_COOKIE] ?? null);
        if (!$dev) out(401, ['error' => 'Přihlas se.', 'login' => true]);
        if ($method === 'GET' && $route === 'hodiny') {
            owner_cookie($_COOKIE[OWNER_COOKIE], OWNER_DEVICE_DAYS);   // a device in use stays signed in
            out(200, hours_view() + ['devices' => owner_devices($dev['id'])]);
        }
        if ($method === 'POST' && $route === 'hodiny/ulozit') {
            $v = hours_save(is_array($j['week'] ?? null) ? $j['week'] : [], is_array($j['days'] ?? null) ? $j['days'] : [],
                (int)($j['version'] ?? -1), $dev['label'] . ' ' . $dev['id']);
            out(200, ['version' => $v]);
        }
        if ($method === 'POST' && $route === 'hodiny/odhlasit') {
            $id = (string)($j['id'] ?? $dev['id']);
            owner_logout($id, $dev['id']);
            if ($id === $dev['id']) owner_cookie('', 0);
            out(200, ['devices' => $id === $dev['id'] ? [] : owner_devices($dev['id'])]);
        }
    }

    out(404, ['error' => 'not found']);
} catch (ClientError $e) {
    out($e->getCode() ?: 400, ['error' => $e->getMessage()]);
} catch (Throwable $e) {
    log_line('API_ERROR', ['route' => $route, 'error' => $e->getMessage()]);
    out(500, ['error' => 'Něco se pokazilo. Zkuste to prosím za chvíli, nebo mi zavolejte.']);
}
