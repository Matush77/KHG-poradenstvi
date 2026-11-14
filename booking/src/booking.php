<?php
/* Booking core: the client's choice, the slot hold, Stripe Checkout, booking after payment, cancellation,
   the safety net. Every path that books after a payment goes through book_paid(), keyed by the payment id. */
declare(strict_types=1);

const FORMATS = ['online', 'osobne'];

class ClientError extends RuntimeException {}   // shown to the client as is (Czech), with its HTTP code

function prague(string $iso): DateTimeImmutable
{
    return new DateTimeImmutable($iso, new DateTimeZone('Europe/Prague'));
}

/* The session start from day + time, in Prague time (summer time included). */
function slot_start(string $den, string $cas): DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $den) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $cas, $m)) {
        throw new ClientError('Neplatný termín.', 400);
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $den, new DateTimeZone('Europe/Prague'));
    if (!$d || $d->format('Y-m-d') !== $den) throw new ClientError('Neplatný termín.', 400);
    return $d->setTime((int)$m[1], (int)$m[2]);
}

function cancel_token(string $payId): string
{
    $raw = substr(hash_hmac('sha256', $payId, cfg()['secret'], true), 0, 18);
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function token_ok(string $payId, string $token): bool
{
    return $payId !== '' && hash_equals(cancel_token($payId), $token);
}

/* The owner's links (move, cancel with refund) use another key, so a client's link can never do what hers do. */
function owner_token(string $payId): string
{
    return cancel_token('owner:' . $payId);
}

function owner_token_ok(string $payId, string $token): bool
{
    return $payId !== '' && !str_starts_with($payId, 'owner:') && hash_equals(owner_token($payId), $token);
}

function owner_url(string $payId): string
{
    return site() . cfg()['owner_page'] . '?' . http_build_query(['b' => $payId, 't' => owner_token($payId)]);
}

function cancel_url(string $payId): string
{
    return site() . cfg()['cancel_page'] . '?' . http_build_query(['b' => $payId, 't' => cancel_token($payId)]);
}

/* The first $chars characters (not bytes: substr can split a Czech letter, and the text then fails as UTF-8).
   Text that is not valid UTF-8 at all becomes ''. */
function cut(string $s, int $chars): string
{
    if (!preg_match('//u', $s)) return '';
    preg_match('/^.{0,' . $chars . '}/su', $s, $m);
    return trim($m[0]);
}

/* What the browser sends: {f, date, time, name, email, phone}. Checked here; the price is never taken from it. */
function parse_choice(array $b): array
{
    $forma = in_array($b['f'] ?? '', FORMATS, true) ? $b['f'] : 'online';
    $start = slot_start(substr((string)($b['date'] ?? ''), 0, 10), (string)($b['time'] ?? ''));
    [$first, $last] = slot_window();
    if ($start < $first) throw new ClientError('Tenhle termín už nejde rezervovat. Vyberte prosím jiný.', 400);
    if ($start > $last) throw new ClientError('Tak daleko dopředu se zatím rezervovat nedá.', 400);
    $email = trim((string)($b['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ClientError('E-mail nevypadá správně.', 400);
    if ($forma === 'online' && $email === '') throw new ClientError('Vyplňte prosím e-mail, pošlu na něj odkaz na videohovor.', 400);
    $meta = ['zdroj' => 'web', 'kopie' => site_copy(), 'forma' => $forma, 'den' => $start->format('Y-m-d'), 'cas' => $start->format('G:i'),
        'jmeno' => cut(trim((string)($b['name'] ?? '')), 100), 'email' => substr($email, 0, 200),
        'telefon' => cut(trim((string)($b['phone'] ?? '')), 40)];
    return [$start, $meta];
}

/* The visitor, for the hold limit: a keyed hash of the IP, so the database never holds an address.
   Live: check that REMOTE_ADDR is the visitor and not the hosting's proxy. */
function visitor(): string
{
    return substr(hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), cfg()['secret']), 0, 16);
}

/* This browser, so a client back from Stripe's page (the back button or Stripe's own back link) still sees the time
   they were paying for and can pay for it again: their own hold must not block them. A random cookie only for /api,
   of which only a keyed hash is stored. Not the IP: a household or a mobile network shares one. */
const HOLD_COOKIE = 'khg_rezervace';

function browser_id(bool $create = false): ?string
{
    $t = (string)($_COOKIE[HOLD_COOKIE] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{22,64}$/', $t)) {
        if (!$create) return null;
        $t = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        $_COOKIE[HOLD_COOKIE] = $t;
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off' || !lab();
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie(HOLD_COOKIE, $t, ['expires' => time() + 86400, 'path' => '/api', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
        }
    }
    return substr(hash_hmac('sha256', 'browser:' . $t, cfg()['secret']), 0, 16);
}

/* Ends a hold's Stripe page so it can no longer be paid. False when it is already paid (the booking is on its way). */
function expire_hold(array $h, string $failMsg): bool
{
    if (!$h['pay_id']) return true;
    [$code] = stripe('POST', 'checkout/sessions/' . rawurlencode($h['pay_id']) . '/expire');
    if ($code === 200) return true;
    // Stripe refuses when the session is already paid or already expired
    [$code, $sess] = stripe('GET', 'checkout/sessions/' . rawurlencode($h['pay_id']));
    $st = $code === 200 ? ($sess['status'] ?? '') : '';
    if ($st === 'complete') return false;
    if ($st !== 'expired') throw new ClientError($failMsg, 429);
    return true;
}

/* The same browser pays for the same time again (it came back from Stripe's page): its earlier Stripe page is ended
   first, so the time can never be paid twice, and the new payment takes its place. */
function release_own_hold(?string $browser, string $slot): void
{
    if (!$browser) return;
    $s = db()->prepare('SELECT slot, pay_id FROM holds WHERE slot = ? AND browser = ? AND expires_at >= ?');
    $s->execute([$slot, $browser, time()]);
    if (!$h = $s->fetch()) return;
    if (!expire_hold($h, 'Platbu se teď nepodařilo obnovit. Zkuste to prosím za chvíli.')) {
        throw new ClientError('Tenhle termín už máte zaplacený. Potvrzení přijde e-mailem.', 409);
    }
    db()->prepare('DELETE FROM holds WHERE slot = ? AND browser = ?')->execute([$slot, $browser]);
    log_line('HOLD_RELEASED', ['slot' => $slot, 'pay' => $h['pay_id'], 'why' => 'same browser again']);
}

/* One visitor may have only a few open payments (each blocks a time for 30 minutes). Going back and forth
   between times is normal, so a new one frees the visitor's oldest instead of refusing: someone trying to
   block the calendar can never hold more than the limit. */
function cap_holds(string $who, int $now): void
{
    $s = db()->prepare('SELECT slot, pay_id FROM holds WHERE who = ? AND expires_at >= ? ORDER BY expires_at');
    $s->execute([$who, $now]);
    $open = $s->fetchAll();
    foreach (array_slice($open, 0, max(0, count($open) - cfg()['holds_per_visitor'] + 1)) as $h) {
        // paid, being booked: its hold must stay
        if (!expire_hold($h, 'Máte rozpracovaných víc rezervací najednou. Dokončete prosím jednu z nich, nebo to zkuste za půl hodiny.')) continue;
        db()->prepare('DELETE FROM holds WHERE slot = ? AND who = ?')->execute([$h['slot'], $who]);
        log_line('HOLD_RELEASED', ['slot' => $h['slot'], 'pay' => $h['pay_id'], 'why' => 'visitor limit']);
    }
}

/* Step 2 -> Stripe. Holds the slot for as long as the Stripe page lives (30 min), so two people can't pay for it. */
function create_checkout(array $b): string
{
    [$start, $meta] = parse_choice($b);
    $c = cfg();
    $slot = $start->format(DATE_ATOM);
    $now = time();
    $stripeExpiry = $now + ($c['hold_minutes'] + 1) * 60;     // Stripe needs at least 30 minutes
    db()->prepare('DELETE FROM holds WHERE expires_at < ?')->execute([$now]);   // outside the transaction: kept even when the slot is refused
    $who = visitor();
    $browser = browser_id(true);
    release_own_hold($browser, $slot);
    cap_holds($who, $now);
    db_tx(function (PDO $pdo) use ($slot, $stripeExpiry, $who, $browser) {
        $s = $pdo->prepare("SELECT (SELECT COUNT(*) FROM holds WHERE slot = :s) + (SELECT COUNT(*) FROM bookings WHERE slot = :s AND status IN ('pending','booked','failed'))");
        $s->execute(['s' => $slot]);
        if ((int)$s->fetchColumn() > 0) throw new ClientError('Tenhle termín si právě někdo rezervuje. Vyberte prosím jiný.', 409);
        $pdo->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser) VALUES (?, NULL, ?, ?, ?)')->execute([$slot, $stripeExpiry + 120, $who, $browser]);
    });
    $release = fn() => db()->prepare('DELETE FROM holds WHERE slot = ? AND pay_id IS NULL')->execute([$slot]);
    try {
        // still inside her hours for this format and nothing busy: her calendars may have changed since the page loaded
        if (!in_array($meta['cas'], free_slots($start, false)[$meta['forma']][$meta['den']] ?? [], true)) {
            throw new ClientError('Tenhle termín se mezitím obsadil. Vyberte prosím jiný.', 409);
        }
        $back = $c['booking_page'] . '?' . http_build_query(['forma' => $meta['forma'], 'den' => $meta['den'], 'cas' => $meta['cas']]);
        [$code, $s] = stripe('POST', 'checkout/sessions', [
            'mode' => 'payment', 'locale' => 'cs', 'expires_at' => $stripeExpiry,
            'customer_email' => $meta['email'] ?: null, 'metadata' => $meta,
            'payment_intent_data' => ['description' => cz_label($meta['forma'], $start), 'metadata' => $meta],
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'czk', 'unit_amount' => $c['price'],
                'product_data' => ['name' => cz_label($meta['forma'], $start)]]]],
            'success_url' => site() . $back . '&paid={CHECKOUT_SESSION_ID}',
            'cancel_url' => site() . $back . '&krok=2',
        ]);
        if ($code !== 200 || empty($s['url'])) {
            log_line('CHECKOUT_FAILED', ['code' => $code, 'error' => $s['error']['message'] ?? null]);
            throw new ClientError('Platbu se teď nepodařilo založit. Zkuste to prosím za chvíli.', 502);
        }
        db()->prepare('UPDATE holds SET pay_id = ? WHERE slot = ?')->execute([$s['id'], $slot]);
        $note = cut(str_replace("\r", '', trim((string)($b['note'] ?? ''))), 1500);
        if ($note !== '') {
            db()->prepare('INSERT OR REPLACE INTO notes (pay_id, note, created_at) VALUES (?, ?, ?)')->execute([$s['id'], $note, time()]);
        }
        log_line('HOLD', ['slot' => $slot, 'pay' => $s['id']]);
        return $s['url'];
    } catch (Throwable $e) {
        $release();
        if ($e instanceof ClientError) throw $e;
        log_line('CHECKOUT_ERROR', ['error' => $e->getMessage()]);
        throw new ClientError('Rezervace teď nejde dokončit. Zkuste to prosím za chvíli, nebo mi zavolejte.', 503);
    }
}

/* A paid Checkout Session (fetched from Stripe, never taken from the browser or the webhook body) -> booking.
   Safe to call any number of times, from any path, also at the same time. */
function book_paid(array $s): ?array
{
    if (($s['payment_status'] ?? '') !== 'paid' || empty($s['metadata']['den'])) return null;
    $payId = $s['id'];
    $meta = $s['metadata'];
    // in person the form asks only for a phone, but Stripe's page always asks for an e-mail: use it, so the
    // client gets the confirmation and the cancel link
    if (($meta['email'] ?? '') === '' && filter_var($s['customer_details']['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $meta['email'] = substr($s['customer_details']['email'], 0, 200);
    }
    $start = slot_start($meta['den'], $meta['cas']);
    $claimed = db_tx(function (PDO $pdo) use ($payId, $meta, $start) {
        if (booking_get($payId)) return false;
        $n = $pdo->prepare('SELECT note FROM notes WHERE pay_id = ?');
        $n->execute([$payId]);
        $pdo->prepare('INSERT INTO bookings (pay_id, slot, forma, email, phone, name, note, status, owner_mailed, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', 0, ?, ?)')->execute([$payId, $start->format(DATE_ATOM), $meta['forma'],
            $meta['email'] ?: null, $meta['telefon'] ?: null, $meta['jmeno'] ?: null, $n->fetchColumn() ?: null, time(), time()]);
        $pdo->prepare('DELETE FROM notes WHERE pay_id = ?')->execute([$payId]);
        $pdo->prepare('DELETE FROM holds WHERE pay_id = ? OR slot = ?')->execute([$payId, $start->format(DATE_ATOM)]);
        return true;
    });
    if (!$claimed) return booking_get($payId);
    return finish_booking($payId);
}

/* Calendar event (+ Meet) and the e-mail. A failure is recorded and retried by the safety net. */
function finish_booking(string $payId): array
{
    $b = booking_get($payId);
    $start = prague($b['slot']);
    $online = $b['forma'] !== 'osobne';
    try {
        if (!$b['event_id']) {
            $clash = (bool)gcal_busy($start, $start->modify('+50 minutes'));
            $contact = $online ? $b['email'] : $b['phone'];
            $title = (lab() ? 'TEST · ' : '') . ($online ? 'Online' : 'Osobně') . ' · ' . ($b['name'] ?: 'klient');
            $note = 'Rezervace z webu' . (lab() ? ' (lab, Stripe test)' : '') . '. Kontakt: ' . ($contact ?: '-') . '. Platba: ' . $payId;
            $ev = gcal_create($start, $title, $note, $online, $online ? $b['email'] : null);
            booking_update($payId, ['event_id' => $ev['id'], 'meet' => $ev['hangoutLink'] ?? null, 'clash' => (int)$clash, 'status' => 'booked', 'error' => null]);
            log_line($clash ? 'BOOKED_CLASH' : 'BOOKED', ['pay' => $payId, 'slot' => $b['slot']]);
            $b = booking_get($payId);
        }
    } catch (Throwable $e) {
        booking_update($payId, ['status' => $b['event_id'] ? 'booked' : 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
        log_line('BOOKING_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);
        return booking_get($payId);
    }
    // the two e-mails separately: one failing doesn't hold back the other; the safety net retries both
    if (!$b['mail_sent'] && $b['email']) {
        try {
            mail_send($payId, mail_build($b, $start, cancel_url($payId)));
            booking_update($payId, ['mail_sent' => 1]);
            log_line('MAILED', ['pay' => $payId]);
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);
        }
    }
    if (!$b['owner_mailed']) {
        try {
            mail_send($payId . '-owner', mail_owner_new($b, $start, owner_url($payId)));
            booking_update($payId, ['owner_mailed' => 1]);
            log_line('OWNER_MAILED', ['pay' => $payId]);
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'owner', 'error' => $e->getMessage()]);
        }
    }
    return booking_get($payId);
}

/* Client cancels from the e-mail link. 36+ hours before: full refund. Later: no refund. Always: the slot frees up. */
function cancel_booking(string $payId, ?DateTimeImmutable $now = null): array
{
    $b = booking_get($payId);
    if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
    if ($b['status'] === 'cancelled') return $b;
    if ($b['status'] !== 'booked') throw new ClientError('Rezervace se ještě zpracovává. Stačí to zkusit za pár minut, případně zavolat na ' . PHONE . '.', 409);
    $now ??= new DateTimeImmutable();
    $hours = (prague($b['slot'])->getTimestamp() - $now->getTimestamp()) / 3600;
    if ($hours <= 0) throw new ClientError('Sezení už začalo nebo proběhlo, zrušit ho nejde.', 400);
    $fields = ['status' => 'cancelled', 'cancelled_by' => 'client', 'hours_before' => round($hours, 1)];
    if ($hours >= cfg()['free_cancel_hours']) {
        [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($payId));
        if ($code !== 200 || empty($s['payment_intent'])) throw new ClientError('Zrušení se teď nepovedlo. Stačí to zkusit znovu.', 502);
        // the idempotency key makes a double click (or two tabs) refund only once
        [$code, $r] = stripe('POST', 'refunds', ['payment_intent' => $s['payment_intent'], 'reason' => 'requested_by_customer',
            'metadata' => ['duvod' => sprintf('zruseni klientem %.0f h predem', $hours)]], 'cancel-' . $payId);
        if ($code !== 200) {
            log_line('REFUND_FAILED', ['pay' => $payId, 'code' => $code, 'error' => $r['error']['message'] ?? null]);
            throw new ClientError('Vrácení peněz se nepovedlo, termín zůstává. Pomůže telefon: ' . PHONE . '.', 502);
        }
        $fields += ['refund_id' => $r['id'], 'refund_status' => $r['status']];
    }
    booking_update($payId, $fields);   // before the event goes, so the calendar sync never reads it as her deletion
    if ($b['event_id']) event_gone($payId, $b['event_id']);
    log_line('CANCELLED', ['pay' => $payId, 'hours' => $fields['hours_before'], 'refund' => $fields['refund_id'] ?? null]);
    $b = booking_get($payId);
    try {
        mail_send($payId . '-cancelled', mail_owner_cancelled($b, prague($b['slot']), owner_url($payId)));
    } catch (Throwable $e) {
        log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'owner', 'error' => $e->getMessage()]);
    }
    // the client gets it in writing too (owner, 14 Nov): refunded or not
    if ($b['email']) {
        try {
            mail_send($payId . '-client-cancelled', mail_client_cancelled($b, prague($b['slot']), false, $b['refund_id'] !== null));
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'client', 'error' => $e->getMessage()]);
        }
    }
    return $b;
}

/* Deletes the event of a booking already marked cancelled. A failure only leaves a stale event in her calendar. */
function event_gone(string $payId, string $eventId): void
{
    try {
        gcal_delete($eventId);
    } catch (Throwable $e) {
        log_line('EVENT_DELETE_FAILED', ['pay' => $payId, 'error' => $e->getMessage()]);
    }
}

/* Full refund through Stripe; the idempotency key makes a second click (or a second tab) refund only once. */
function refund_full(string $payId, string $why): array
{
    [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($payId));
    if ($code !== 200 || empty($s['payment_intent'])) throw new ClientError('Platbu se teď nepodařilo najít. Zkus to prosím znovu.', 502);
    [$code, $r] = stripe('POST', 'refunds', ['payment_intent' => $s['payment_intent'], 'reason' => 'requested_by_customer',
        'metadata' => ['duvod' => $why]], 'cancel-' . $payId);
    if ($code !== 200) {
        log_line('REFUND_FAILED', ['pay' => $payId, 'code' => $code, 'error' => $r['error']['message'] ?? null]);
        throw new ClientError('Vrácení peněz se nepovedlo, nic se nezměnilo. Zkus to prosím znovu.', 502);
    }
    return ['refund_id' => $r['id'], 'refund_status' => $r['status']];
}

/* The owner cancels (any time, always a full refund), or refunds a session the client cancelled late. The client gets an e-mail. */
function owner_cancel(string $payId, ?DateTimeImmutable $now = null): array
{
    $b = booking_get($payId);
    if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
    if ($b['refund_id']) return $b;                                   // already refunded: nothing more to do
    if (in_array($b['status'], ['pending', 'failed'], true)) throw new ClientError('Rezervace se ještě zpracovává. Zkus to prosím za pár minut.', 409);
    $refundOnly = $b['status'] === 'cancelled';
    $now ??= new DateTimeImmutable();
    $fields = refund_full($payId, $refundOnly ? 'vraceno terapeutkou po zruseni' : 'zruseno terapeutkou');
    if (!$refundOnly) {
        $fields += ['status' => 'cancelled', 'cancelled_by' => 'owner',
            'hours_before' => round((prague($b['slot'])->getTimestamp() - $now->getTimestamp()) / 3600, 1)];
    }
    booking_update($payId, $fields);
    if (!$refundOnly && $b['event_id']) event_gone($payId, $b['event_id']);
    log_line('OWNER_CANCELLED', ['pay' => $payId, 'refund' => $fields['refund_id'], 'refund_only' => $refundOnly]);
    $b = booking_get($payId);
    if ($b['email']) {
        try {
            // deleted by her in the calendar: the client hasn't heard anything yet, so "I had to cancel, refunding"
            mail_send($payId . '-cancel', mail_client_cancelled($b, prague($b['slot']), $refundOnly && $b['cancelled_by'] !== 'calendar'));
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);
        }
    }
    return $b;
}

/* A move to another free time of the same format. The calendar event (and its Meet link) moves with it;
   the client gets "Změna termínu" with a new .ics. No new payment, the client's link stays the same.
   The owner moves any time. The client (decision 14 Nov): as often as they like, but only while a free
   cancel is still possible (36+ hours before the time booked now); later it is a cancellation. She gets
   an e-mail every time, with the count, so a client who keeps postponing stands out. */
function move_booking(string $payId, string $den, string $cas, string $by, ?DateTimeImmutable $now = null): array
{
    $client = $by === 'client';
    $b = booking_get($payId);
    if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
    if ($b['status'] !== 'booked') throw new ClientError($client ? 'Tuhle rezervaci už přesunout nejde.' : 'Přesunout jde jen platnou rezervaci.', 409);
    $now ??= new DateTimeImmutable();
    $old = prague($b['slot']);
    if ($old <= $now) throw new ClientError('Sezení už začalo nebo proběhlo.', 400);
    if ($client && ($old->getTimestamp() - $now->getTimestamp()) / 3600 < cfg()['free_cancel_hours']) {
        throw new ClientError('Do sezení zbývá méně než 36 hodin, přesun už není možný. Pomůže telefon: ' . PHONE . '.', 409);
    }
    $new = slot_start($den, $cas);
    if ($new == $old) throw new ClientError('To je stejný termín.', 400);
    $taken = $client ? 'Tenhle termín už není volný, je potřeba vybrat jiný.' : 'Tenhle termín už není volný. Vyber prosím jiný.';
    if (!in_array($new->format('G:i'), free_slots($new)[$b['forma']][$new->format('Y-m-d')] ?? [], true)) throw new ClientError($taken, 409);
    $slot = $new->format(DATE_ATOM);
    db_tx(function (PDO $pdo) use ($slot, $payId, $taken, $client) {
        $s = $pdo->prepare("SELECT (SELECT COUNT(*) FROM holds WHERE slot = :s AND expires_at >= :now)
            + (SELECT COUNT(*) FROM bookings WHERE slot = :s AND status IN ('pending','booked','failed') AND pay_id != :p)");
        $s->execute(['s' => $slot, 'now' => time(), 'p' => $payId]);
        if ((int)$s->fetchColumn() > 0) throw new ClientError($taken, 409);
        $pdo->prepare('UPDATE bookings SET slot = ?, moves = moves + 1, client_moves = client_moves + ?, mail_sent = 0, clash = 0, reminded = 0, moved_at = ?, updated_at = ? WHERE pay_id = ?')
            ->execute([$slot, $client ? 1 : 0, time(), time(), $payId]);
    });
    if ($b['event_id']) {
        try {
            gcal_move($b['event_id'], $new);
        } catch (Throwable $e) {
            booking_update($payId, ['slot' => $b['slot'], 'moves' => $b['moves'], 'client_moves' => $b['client_moves'], 'mail_sent' => $b['mail_sent'],
                'reminded' => $b['reminded'], 'moved_at' => $b['moved_at']]);
            log_line('MOVE_FAILED', ['pay' => $payId, 'by' => $by, 'error' => $e->getMessage()]);
            throw new ClientError($client ? 'Termín se teď nepodařilo změnit, platí ten původní. Stačí to zkusit znovu.'
                : 'Kalendář se teď nepodařilo upravit, termín zůstává původní. Zkus to prosím znovu.', 502);
        }
    }
    log_line('MOVED', ['pay' => $payId, 'by' => $by, 'from' => $b['slot'], 'to' => $slot]);
    return moved_mails(booking_get($payId), $old, $by);
}

/* After any move (her button, the client's, or her drag in Google Calendar): the client's "Změna termínu";
   if the client moved, also a note to her. */
function moved_mails(array $b, DateTimeImmutable $old, string $by): array
{
    $payId = $b['pay_id'];
    $new = prague($b['slot']);
    if ($b['email']) {
        try {   // if this fails, the safety net sends a normal confirmation of the new time
            mail_send($payId . '-move-' . $b['moves'], mail_build($b, $new, cancel_url($payId), $old, $by === 'client'));
            booking_update($payId, ['mail_sent' => 1]);
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);
        }
    }
    if ($by === 'client') {
        try {
            mail_send($payId . '-moved-' . $b['moves'], mail_owner_moved($b, $new, $old, owner_url($payId)));
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'owner', 'error' => $e->getMessage()]);
        }
    }
    return booking_get($payId);
}

function owner_move(string $payId, string $den, string $cas): array
{
    return move_booking($payId, $den, $cas, 'owner');
}

function client_move(string $payId, string $den, string $cas, ?DateTimeImmutable $now = null): array
{
    return move_booking($payId, $den, $cas, 'client', $now);
}

/* Her own edits in Google Calendar (cron, every minute; one Google call):
   - she dragged a session to another time: the booking follows, the client gets "Změna termínu";
   - she deleted it: the booking is cancelled (time free again), no refund yet; she gets an e-mail with
     a button to refund and tell the client;
   - an online client declined the invitation in their own calendar: she gets an e-mail, nothing else
     changes (a decline is not a cancellation: no refund, the time stays booked). */
function sync_calendar(?int $now = null): array
{
    $now ??= time();
    $done = ['moved' => [], 'deleted' => [], 'declined' => []];
    $since = (int)(state_get('calendar_sync') ?? ($now - 86400));
    $events = gcal_changed($since - 120);   // a little overlap: handling an event twice changes nothing
    $find = db()->prepare("SELECT * FROM bookings WHERE event_id = ? AND status = 'booked'");
    foreach ($events as $ev) {
        $find->execute([(string)($ev['id'] ?? '')]);
        $b = $find->fetch();
        if (!$b) continue;                                            // not ours, or already cancelled through the site
        if ($b['updated_at'] > $now - 30) continue;                   // the site is changing it right now; the next run looks again
        $payId = $b['pay_id'];
        $old = prague($b['slot']);
        if (($ev['status'] ?? '') === 'cancelled') {
            booking_update($payId, ['status' => 'cancelled', 'cancelled_by' => 'calendar',
                'hours_before' => round(($old->getTimestamp() - $now) / 3600, 1)]);
            log_line('DELETED_IN_CALENDAR', ['pay' => $payId]);
            try {
                mail_send($payId . '-deleted', mail_owner_deleted(booking_get($payId), $old, owner_url($payId)));
            } catch (Throwable $e) {
                log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'owner', 'error' => $e->getMessage()]);
            }
            $done['deleted'][] = $payId;
            continue;
        }
        $start = isset($ev['start']['dateTime']) ? (new DateTimeImmutable($ev['start']['dateTime']))->setTimezone(new DateTimeZone('Europe/Prague')) : null;
        if ($start && $start->getTimestamp() !== $old->getTimestamp()) {
            booking_update($payId, ['slot' => $start->format(DATE_ATOM), 'moves' => $b['moves'] + 1, 'mail_sent' => 0, 'clash' => 0, 'reminded' => 0, 'moved_at' => $now]);
            log_line('MOVED_IN_CALENDAR', ['pay' => $payId, 'from' => $b['slot'], 'to' => $start->format(DATE_ATOM)]);
            if ($start->getTimestamp() > $now) moved_mails(booking_get($payId), $old, 'calendar');
            else booking_update($payId, ['mail_sent' => 1]);         // moved into the past (a record): nobody to tell
            $done['moved'][] = $payId;
        }
        $guest = $b['email'] ? strtolower($b['email']) : null;
        foreach ($ev['attendees'] ?? [] as $a) {
            if ($guest && strtolower($a['email'] ?? '') === $guest && ($a['responseStatus'] ?? '') === 'declined' && !$b['declined_mailed']) {
                $b = booking_get($payId);
                try {
                    mail_send($payId . '-declined', mail_owner_declined($b, prague($b['slot']), owner_url($payId)));
                    booking_update($payId, ['declined_mailed' => 1]);
                } catch (Throwable $e) {
                    log_line('MAIL_ERROR', ['pay' => $payId, 'to' => 'owner', 'error' => $e->getMessage()]);
                }
                log_line('GUEST_DECLINED', ['pay' => $payId]);
                $done['declined'][] = $payId;
            }
        }
    }
    state_set('calendar_sync', (string)$now);
    return $done;
}

/* What the owner's page shows. */
function owner_view(array $b, ?DateTimeImmutable $now = null): array
{
    return booking_view($b, $now) + ['name' => $b['name'], 'email' => $b['email'], 'phone' => $b['phone'], 'note' => $b['note'],
        'clash' => (bool)$b['clash'], 'cancelled_by' => $b['cancelled_by'], 'moves' => (int)$b['moves'], 'hours_before' => $b['hours_before']];
}

/* What the cancel page shows. */
function booking_view(array $b, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable();
    $start = prague($b['slot']);
    $deadline = $start->modify('-' . cfg()['free_cancel_hours'] . ' hours');
    return ['forma' => $b['forma'], 'den' => $start->format('Y-m-d'), 'cas' => $start->format('G:i'),
        'when' => cz_at($start), 'deadline' => cz_until($deadline),
        'started' => $now >= $start, 'refund' => $now <= $deadline,
        'cancelled' => $b['status'] === 'cancelled', 'refunded' => (bool)$b['refund_id'],
        'processing' => in_array($b['status'], ['pending', 'failed'], true),
        'can_move' => $b['status'] === 'booked' && $now <= $deadline];
}

/* The reminders, from the cron (every minute), to every booked client with an e-mail: one 24 hours before,
   one 10 minutes before (the flags in `reminded`; a move clears them, so the new time gets both).
   The day-before one is skipped when it would only repeat a fresh confirmation (booked or changed in the
   last 6 hours) or come too late to matter (under 2 hours left, the short one follows anyway). */
const REMIND_DAY = 1, REMIND_SOON = 2;
function send_reminders(?int $now = null): array
{
    $now ??= time();
    $c = cfg();
    $done = [];
    $s = db()->prepare("SELECT * FROM bookings WHERE status = 'booked' AND reminded < 3 AND email IS NOT NULL");
    $s->execute();
    foreach ($s->fetchAll() as $b) {
        $start = prague($b['slot']);
        $left = $start->getTimestamp() - $now;
        if ($left <= 0) continue;
        $flags = (int)$b['reminded'];
        $kind = null;
        if (!($flags & REMIND_SOON) && $left <= $c['remind_soon_minutes'] * 60) {
            $kind = 'soon';
            $flags |= REMIND_SOON | REMIND_DAY;                       // the day-before one is pointless now
        } elseif (!($flags & REMIND_DAY) && $left <= $c['remind_day_hours'] * 3600) {
            $fresh = max((int)$b['created_at'], (int)($b['moved_at'] ?? 0)) > $now - 6 * 3600;
            $flags |= REMIND_DAY;
            if (!$fresh && $left >= 2 * 3600) $kind = 'day';
        }
        if ($flags === (int)$b['reminded']) continue;
        try {
            if ($kind) mail_send($b['pay_id'] . '-remind-' . $kind . '-' . $b['moves'], mail_reminder($b, $start, $kind, $now));
            booking_update($b['pay_id'], ['reminded' => $flags]);
            if ($kind) $done[] = $b['pay_id'] . ':' . $kind;
        } catch (Throwable $e) {
            log_line('MAIL_ERROR', ['pay' => $b['pay_id'], 'what' => 'reminder ' . $kind, 'error' => $e->getMessage()]);
        }
    }
    return $done;
}

/* Safety net (cron every few minutes, next to the webhook): books paid sessions nobody booked (closed tab,
   lost webhook), retries failed bookings and unsent e-mails, clears old holds. */
function sweep(): array
{
    $done = ['booked' => [], 'retried' => [], 'mailed' => []];
    db()->prepare('DELETE FROM holds WHERE expires_at < ?')->execute([time()]);
    db()->prepare('DELETE FROM notes WHERE created_at < ?')->execute([time() - 2 * 86400]);   // never paid: forget what they wrote
    // first her calendar edits (a session she just moved gets its reminders at the new time), then the reminders:
    // neither needs Stripe, so a Stripe outage below doesn't hold them back
    try {
        $done += array_filter(sync_calendar());
    } catch (Throwable $e) {
        log_line('SYNC_ERROR', ['error' => $e->getMessage()]);   // the next run catches up from the last good check
    }
    if ($r = send_reminders()) $done['reminded'] = $r;
    [$code, $page] = stripe('GET', 'checkout/sessions', ['status' => 'complete', 'limit' => 100, 'created' => ['gte' => time() - 86400]]);
    if ($code !== 200) throw new RuntimeException('Stripe list failed: HTTP ' . $code);
    foreach ($page['data'] ?? [] as $s) {
        // only this copy's payments: the test site and the lab share one Stripe test account (14 Nov: the test site's
        // first run booked the lab's payments again)
        if (($s['payment_status'] ?? '') === 'paid' && ($s['metadata']['zdroj'] ?? '') === 'web'
            && ($s['metadata']['kopie'] ?? 'khg') === site_copy() && !booking_get($s['id'])) {
            book_paid($s);
            $done['booked'][] = $s['id'];
        }
    }
    $s = db()->prepare("SELECT pay_id, status FROM bookings WHERE status = 'failed' OR (status = 'pending' AND updated_at < ?)
        OR (status = 'booked' AND mail_sent = 0 AND email IS NOT NULL) OR (status = 'booked' AND owner_mailed = 0)");
    $s->execute([time() - 300]);
    foreach ($s->fetchAll() as $row) {
        $b = finish_booking($row['pay_id']);
        if ($row['status'] !== 'booked') $done['retried'][] = $row['pay_id'];
        elseif (($b['mail_sent'] || !$b['email']) && $b['owner_mailed']) $done['mailed'][] = $row['pay_id'];
        else $done['mail_failed'][] = $row['pay_id'];   // stays unsent: the next run tries again
    }
    if (array_filter($done)) log_line('SWEEP', $done);
    return $done;
}
