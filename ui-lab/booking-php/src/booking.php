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

/* One way of writing a Czech or Slovak number, so she can read and dial it (16 Nov test run: "600000000" was kept as typed):
   "600000000", "600 000 000", "00420600000000", "420 600-000-000" all become "+420 600 000 000". Other numbers stay as typed. */
function phone_pretty(string $phone): string
{
    $phone = trim($phone);
    $d = (string)preg_replace('/[\s\-.\/()]/', '', $phone);
    if (str_starts_with($d, '00')) $d = '+' . substr($d, 2);
    if (preg_match('/^\d{9}$/', $d)) $d = '+420' . $d;
    if (preg_match('/^\+?(42[01])(\d{3})(\d{3})(\d{3})$/', $d, $m)) return "+$m[1] $m[2] $m[3] $m[4]";
    return $phone;
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
    // in person the phone is how she reaches the client (the page checks the same: at least 9 digits)
    $phone = trim((string)($b['phone'] ?? ''));
    if ($forma === 'osobne' && strlen((string)preg_replace('/\D/', '', $phone)) < 9) {
        throw new ClientError($phone === '' ? 'Vyplňte prosím telefon.' : 'Telefon nevypadá správně.', 400);
    }
    $meta = ['zdroj' => 'web', 'kopie' => site_copy(), 'forma' => $forma, 'den' => $start->format('Y-m-d'), 'cas' => $start->format('G:i'),
        'jmeno' => cut(trim((string)($b['name'] ?? '')), 100), 'email' => substr($email, 0, 200),
        'telefon' => cut(phone_pretty($phone), 40)];
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

/* A hold whose request is still asking Stripe for the payment page (no Stripe id yet). Nobody else may end it:
   that request would hand out a payment page for a time it no longer holds (16 Nov review: two tabs of one
   browser both got a page for the same time). After a minute without a Stripe id the request is dead. */
const HOLD_CREATING_SECONDS = 60;
function hold_creating(array $h, ?int $now = null): bool
{
    if ($h['pay_id']) return false;
    $created = (int)$h['expires_at'] - (cfg()['hold_minutes'] + 1) * 60 - 120;
    return $created > ($now ?? time()) - HOLD_CREATING_SECONDS;
}

/* The same browser pays for the same time again (it came back from Stripe's page): its earlier Stripe page is ended
   first, so the time can never be paid twice, and the new payment takes its place. */
function release_own_hold(?string $browser, string $slot): void
{
    if (!$browser) return;
    $s = db()->prepare('SELECT slot, pay_id, expires_at FROM holds WHERE slot = ? AND browser = ? AND expires_at >= ?');
    $s->execute([$slot, $browser, time()]);
    $h = $s->fetch();
    $s->closeCursor();   // no read left open across Stripe and the write below (see owner_login)
    if (!$h) return;
    if (hold_creating($h)) throw new ClientError('Platba za tenhle termín se právě zakládá v jiném okně. Pokračujte prosím tam, nebo to zkuste za minutu.', 409);
    if (!expire_hold($h, 'Platbu se teď nepodařilo obnovit. Zkuste to prosím za chvíli.')) {
        throw new ClientError('Tenhle termín už máte zaplacený. Potvrzení přijde e-mailem.', 409);
    }
    // only this hold: a newer one for the same time (another tab) is not this request's to end (17 Nov review)
    db()->prepare('DELETE FROM holds WHERE slot = ? AND browser = ? AND pay_id IS ?')->execute([$slot, $browser, $h['pay_id']]);
    log_line('HOLD_RELEASED', ['slot' => $slot, 'pay' => $h['pay_id'], 'why' => 'same browser again']);
}

/* The client came back from Stripe's page to ours without paying (Stripe's back arrow or the phone's back button):
   that tab's Stripe page is ended, so its time is free for everyone at once instead of after Stripe's 30 minutes.
   $pay is the Stripe page the tab opened; another tab of the same browser may still be paying on its own page
   (16 Nov: going back in one tab ended the other's). Without $pay (a page opened before this change) every open
   Stripe page of this browser goes, as before. A page already paid keeps its hold (the booking is on its way);
   one still being created (no Stripe id yet) is left alone. Returns the times freed. */
function holds_to_release(?string $browser, ?string $pay = null): array
{
    if (!$browser) return [];
    $s = db()->prepare('SELECT slot, pay_id FROM holds WHERE browser = ? AND pay_id IS NOT NULL AND expires_at >= ?'
        . ($pay !== null ? ' AND pay_id = ?' : ''));
    $s->execute($pay !== null ? [$browser, time(), $pay] : [$browser, time()]);
    return $s->fetchAll();
}

function release_browser_holds(?string $browser, ?string $pay = null): array
{
    $freed = [];
    foreach (holds_to_release($browser, $pay) as $h) {
        try {
            if (!expire_hold($h, 'later')) continue;
        } catch (ClientError $e) {
            continue;   // Stripe unreachable: the hold simply runs out as before
        }
        // only the hold of the Stripe page just ended (17 Nov review: a slow release deleted the newer hold that
        // replaced it for the same time, and two Stripe pages for one time could both be paid)
        db()->prepare('DELETE FROM holds WHERE slot = ? AND browser = ? AND pay_id = ?')->execute([$h['slot'], $browser, $h['pay_id']]);
        log_line('HOLD_RELEASED', ['slot' => $h['slot'], 'pay' => $h['pay_id'], 'why' => 'back from Stripe']);
        $freed[] = $h['slot'];
    }
    return $freed;
}

/* One visitor may have only a few open payments (each blocks a time for 30 minutes). Going back and forth
   between times is normal, so a new one frees the visitor's oldest instead of refusing: someone trying to
   block the calendar can never hold more than the limit. */
function cap_holds(string $who, int $now): void
{
    $s = db()->prepare('SELECT slot, pay_id, expires_at FROM holds WHERE who = ? AND expires_at >= ? ORDER BY expires_at');
    $s->execute([$who, $now]);
    $open = $s->fetchAll();
    foreach (array_slice($open, 0, max(0, count($open) - cfg()['holds_per_visitor'] + 1)) as $h) {
        if (hold_creating($h, $now)) continue;   // its request is still running: never pulled from under it
        if (hold_paying($h)) continue;           // someone is confirming the payment in their bank right now
        // paid, being booked: its hold must stay
        if (!expire_hold($h, 'Máte rozpracovaných víc rezervací najednou. Dokončete prosím jednu z nich, nebo to zkuste za půl hodiny.')) continue;
        db()->prepare('DELETE FROM holds WHERE slot = ? AND who = ? AND pay_id IS ?')->execute([$h['slot'], $who, $h['pay_id']]);
        log_line('HOLD_RELEASED', ['slot' => $h['slot'], 'pay' => $h['pay_id'], 'why' => 'visitor limit']);
    }
}

/* The hold's payment is under way at the client's bank (3-D Secure) or being processed. The visitor limit counts
   by IP, and phones on mobile data share one: a 4th payment from the same address must not end a stranger's payment
   in the middle of its bank confirmation (review 17 Nov). Unknown (Stripe not answering): not under way. */
function hold_paying(array $h): bool
{
    if (!$h['pay_id']) return false;
    [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($h['pay_id']), ['expand' => ['payment_intent']]);
    $st = $code === 200 && is_array($s['payment_intent'] ?? null) ? ($s['payment_intent']['status'] ?? '') : '';
    return in_array($st, ['requires_action', 'processing', 'requires_capture'], true);
}

/* Step 2 -> Stripe. Holds the slot for as long as the Stripe page lives (30 min), so two people can't pay for it. */
function create_checkout(array $b): array
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
    $nonce = bin2hex(random_bytes(8));
    db_tx(function (PDO $pdo) use ($slot, $stripeExpiry, $who, $browser, $nonce) {
        $s = $pdo->prepare("SELECT (SELECT COUNT(*) FROM holds WHERE slot = :s) + (SELECT COUNT(*) FROM bookings WHERE slot = :s AND status IN ('pending','booked','failed'))");
        $s->execute(['s' => $slot]);
        if ((int)$s->fetchColumn() > 0) throw new ClientError('Tenhle termín si právě někdo rezervuje. Vyberte prosím jiný.', 409);
        $pdo->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, NULL, ?, ?, ?, ?)')->execute([$slot, $stripeExpiry + 120, $who, $browser, $nonce]);
    });
    $release = fn() => db()->prepare('DELETE FROM holds WHERE slot = ? AND nonce = ?')->execute([$slot, $nonce]);
    try {
        // still inside her hours for this format and nothing busy: her calendars may have changed since the page loaded
        if (!in_array($meta['cas'], free_slots($start, false)[$meta['forma']][$meta['den']] ?? [], true)) {
            throw new ClientError('Tenhle termín se mezitím obsadil. Vyberte prosím jiný.', 409);
        }
        $back = $c['booking_page'] . '?' . http_build_query(['stripe' => 1, 'pay' => 0, 'forma' => $meta['forma'], 'den' => $meta['den'], 'cas' => $meta['cas']]);
        [$code, $s] = stripe('POST', 'checkout/sessions', [
            'mode' => 'payment', 'locale' => 'cs', 'expires_at' => $stripeExpiry,
            'payment_method_types' => ['card'],     // card, with Google Pay / Apple Pay where the phone has them; no Klarna
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
        $own = db()->prepare('UPDATE holds SET pay_id = ? WHERE slot = ? AND nonce = ?');
        $own->execute([$s['id'], $slot, $nonce]);
        if ($own->rowCount() === 0) {
            // the hold is no longer ours (it ran out, or something removed it): this page must never be paid
            stripe('POST', 'checkout/sessions/' . rawurlencode($s['id']) . '/expire');
            log_line('HOLD_LOST', ['slot' => $slot, 'pay' => $s['id']]);
            throw new ClientError('Tenhle termín si právě někdo rezervuje. Vyberte prosím jiný.', 409);
        }
        $note = cut(str_replace("\r", '', trim((string)($b['note'] ?? ''))), 1500);
        if ($note !== '') {
            db()->prepare('INSERT OR REPLACE INTO notes (pay_id, note, created_at) VALUES (?, ?, ?)')->execute([$s['id'], $note, time()]);
        }
        log_line('HOLD', ['slot' => $slot, 'pay' => $s['id']]);
        return ['url' => $s['url'], 'pay' => $s['id']];   // the page keeps the id to end only its own Stripe page
    } catch (Throwable $e) {
        $release();
        if ($e instanceof ClientError) throw $e;
        log_line('CHECKOUT_ERROR', ['error' => $e->getMessage()]);
        throw new ClientError('Rezervace teď nejde dokončit. Zkuste to prosím za chvíli, nebo mi zavolejte.', 503);
    }
}

/* A paid Checkout Session (fetched from Stripe, never taken from the browser or the webhook body) -> booking.
   Safe to call any number of times, from any path, also at the same time. */
const BOOK_PAID_MAX_AGE_DAYS = 10;
function book_paid(array $s): ?array
{
    if (($s['payment_status'] ?? '') !== 'paid' || empty($s['metadata']['den'])) return null;
    // the test site and the lab share one Stripe test account, and Stripe sends every webhook to every endpoint:
    // a payment made on another copy is never booked here
    if (session_copy($s) !== site_copy()) return null;
    $payId = $s['id'];
    // a payment older than the last webhook retry and the safety net's day, with no booking: a booking deleted under
    // the keep-for-3-years rule. Never brought back from an old link (16 Nov review: name, e-mail and a fresh
    // confirmation came back). Stripe retries a webhook for 3 days; a week leaves room.
    if (isset($s['created']) && (int)$s['created'] < time() - BOOK_PAID_MAX_AGE_DAYS * 86400 && !booking_get($payId)) {
        log_line('OLD_PAYMENT_NOT_BOOKED', ['pay' => $payId]);
        return null;
    }
    $meta = $s['metadata'];
    // in person the form asks only for a phone, but Stripe's page always asks for an e-mail: use it, so the
    // client gets the confirmation and the cancel link
    if (($meta['email'] ?? '') === '' && filter_var($s['customer_details']['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $meta['email'] = substr($s['customer_details']['email'], 0, 200);
    }
    $start = slot_start($meta['den'], $meta['cas']);
    // the session has already started: the payment was booked too late (webhook and safety net both down for a day
    // or more). Never confirmed; refunded like a double payment, both told (17 Nov review: a confirmation for a
    // session in the past, which no refund path would then accept)
    $past = $start->getTimestamp() <= time();
    $pi = payment_intent_id($s);
    $amount = (int)($s['amount_total'] ?? 0);
    // booked late (webhook lost, the safety net found it): she may have refunded it in her Stripe dashboard meanwhile.
    // Such a payment is never confirmed (17 Nov review, second list: a confirmation for money already returned).
    // A fresh payment can't have been refunded yet, so the usual path doesn't ask Stripe once more.
    $refund = null;
    if ($pi && (int)($s['created'] ?? time()) < time() - 600 && !booking_get($payId)) {
        try { $refund = refunds_of($pi, $amount)['full']; } catch (Throwable $e) { /* not known: booked as usual, the safety net compares later */ }
    }
    $claimed = db_tx(function (PDO $pdo) use ($payId, $meta, $start, $past, $pi, $amount, $refund) {
        if (booking_get($payId)) return false;
        // the last check: someone else's payment already has this time. The holds should make this impossible;
        // if it happens anyway, this payment is not booked but refunded, and both of them are told
        $t = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE slot = ? AND status IN ('pending','booked','failed') AND pay_id != ?");
        $t->execute([$start->format(DATE_ATOM), $payId]);
        $taken = (int)$t->fetchColumn() > 0;
        $why = $refund ? 'refunded' : ($taken ? 'double' : ($past ? 'missed' : null));
        $n = $pdo->prepare('SELECT note FROM notes WHERE pay_id = ?');
        $n->execute([$payId]);
        $pdo->prepare('INSERT INTO bookings (pay_id, slot, forma, email, phone, name, note, status, cancelled_by, owner_mailed,
            payment_intent, amount, refund_id, refund_status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$payId, $start->format(DATE_ATOM), $meta['forma'],
            $meta['email'] ?: null, $meta['telefon'] ?: null, $meta['jmeno'] ?: null, $n->fetchColumn() ?: null,
            $why ? 'cancelled' : 'pending', $why, $why ? 1 : 0, $pi, $amount ?: null,
            $refund['id'] ?? null, isset($refund) ? (string)$refund['status'] : null, time(), time()]);
        $pdo->prepare('DELETE FROM notes WHERE pay_id = ?')->execute([$payId]);
        if (!$why) $pdo->prepare('DELETE FROM holds WHERE pay_id = ? OR slot = ?')->execute([$payId, $start->format(DATE_ATOM)]);
        return $why ?? true;
    });
    if (!$claimed) return booking_get($payId);
    if ($claimed === 'refunded') {
        log_line('PAID_ALREADY_REFUNDED', ['pay' => $payId, 'refund' => $refund['id']]);
        mail_queued($payId . '-refund-found', $payId, 'owner_refund_found');
        return booking_get($payId);
    }
    if ($claimed !== true) {
        log_line($claimed === 'double' ? 'DOUBLE_PAYMENT' : 'PAID_TOO_LATE', ['pay' => $payId, 'slot' => $start->format(DATE_ATOM)]);
        return refund_double($payId);
    }
    return finish_booking($payId);
}

/* A payment that is not booked but refunded, the client and she get an e-mail: a time that was already booked
   ('double'), or a session that had already started when the payment was booked ('missed'). If Stripe fails,
   the safety net tries again (such a booking without a refund). */
function refund_double(string $payId): array
{
    try {
        $fields = refund_full($payId, (booking_get($payId)['cancelled_by'] ?? '') === 'missed' ? 'termin uz probehl' : 'termin mezitim obsazen');
        booking_update($payId, $fields);
        log_line('DOUBLE_REFUNDED', ['pay' => $payId, 'refund' => $fields['refund_id']]);
    } catch (Throwable $e) {
        log_line('DOUBLE_REFUND_FAILED', ['pay' => $payId, 'error' => $e->getMessage()]);
        return booking_get($payId);
    }
    refund_watch($payId);
    double_mails($payId);
    return booking_get($payId);
}
function double_mails(string $payId): void
{
    if (booking_get($payId)['email']) mail_queued($payId . '-double', $payId, 'client_double');
    mail_queued($payId . '-double-owner', $payId, 'owner_double');
}

/* --- e-mails that must arrive (16 Nov review: a failed cancellation e-mail was only logged). Each is written down
   first, sent, and crossed off when it went; the safety net sends whatever is left. Rebuilt from the booking when
   sent, so a retry says what is true then. */
/* A kind may carry one value after a "|" (the old time of a move, the e-mail that did not arrive).
   Null: nothing to send any more (it would no longer be true). */
function queued_mail(array $b, string $kind): ?array
{
    [$kind, $arg] = array_pad(explode('|', $kind, 2), 2, '');
    $start = prague($b['slot']);
    $url = owner_url($b['pay_id']);
    $refunded = $b['refund_id'] !== null && !refund_failed($b);
    // the refund Stripe refused since (review 17 Nov: an e-mail that went late still promised the money)
    $delayed = refund_failed($b);
    return match ($kind) {
        'owner_cancelled' => mail_owner_cancelled($b, $start, $url),
        'client_cancelled' => mail_client_cancelled($b, $start, false, $refunded, false, $delayed),   // the client cancelled
        'client_cancel' => mail_client_cancelled($b, $start, false, $refunded, true, $delayed),       // she cancelled, with a refund
        'client_refund' => mail_client_cancelled($b, $start, true, $refunded, false, $delayed),       // refund after a cancellation
        'owner_refund_failed' => refund_failed($b) ? mail_owner_refund_failed($b, $start, $url) : null,   // sorted out since
        // a refund found in Stripe that the site did not make: the session still booked, or a payment never booked
        'owner_refund_found' => $b['refund_id'] && !$delayed && ($b['status'] === 'booked' || $b['cancelled_by'] === 'refunded')
            ? mail_owner_refund_found($b, $start, $url) : null,
        'owner_double' => mail_owner_double($b, $start, $refunded),
        'client_double' => mail_client_double($b, $start),
        // she deleted it in her calendar; once she has refunded it there is nothing to ask her any more
        'owner_deleted' => $b['refund_id'] && !$delayed ? null : mail_owner_deleted($b, $start, $url),
        'owner_declined' => $b['status'] === 'booked' ? mail_owner_declined($b, $start, $url) : null,
        'owner_moved' => mail_owner_moved($b, $start, prague($arg), $url),
        'owner_undeliverable' => mail_owner_undeliverable($b, $start, $url, $arg),
        default => throw new RuntimeException('Unknown queued mail ' . $kind),
    };
}

/* Writes the e-mail down and tries to send it now. True when it went. */
function mail_queued(string $name, string $payId, string $kind): bool
{
    db()->prepare('INSERT OR IGNORE INTO mail_queue (name, pay_id, kind, created_at) VALUES (?, ?, ?, ?)')->execute([$name, $payId, $kind, time()]);
    return mail_queue_send($name, $payId, $kind);
}

/* One sender per e-mail (review 17 Nov: the cron and a web request, or two cron runs, could both send the same one).
   Whoever marks it first sends it; the mark is cleared when the try ends, and one left by a request that died
   is taken over after MAIL_CLAIM_SECONDS (longer than the slowest SMTP conversation). $table, $key, $col and
   $cond are fixed strings from this file, never input. */
const MAIL_CLAIM_SECONDS = 600;
function mail_claim(string $table, string $key, string $col, string $id, string $cond = '1'): bool
{
    $s = db()->prepare("UPDATE $table SET $col = :now WHERE $key = :id AND ($cond) AND ($col IS NULL OR $col < :stale)");
    $s->execute(['now' => time(), 'id' => $id, 'stale' => time() - MAIL_CLAIM_SECONDS]);
    return $s->rowCount() === 1;
}
function mail_unclaim(string $table, string $key, string $col, string $id): void
{
    db()->prepare("UPDATE $table SET $col = NULL WHERE $key = ?")->execute([$id]);
}

/* Retrying ends when the server refused the e-mail for good (a dead address), or after this long (review 17 Nov:
   such an e-mail was retried every 5 minutes for ever). She is told when a client's e-mail did not arrive. */
const MAIL_GIVE_UP_DAYS = 3;
function mail_gave_up(string $payId, string $what, string $to, string $error): void
{
    log_line('MAIL_GAVE_UP', ['pay' => $payId, 'mail' => $what, 'error' => substr($error, 0, 200)]);
    if ($to !== '' && $to !== OWNER_MAIL) mail_queued($payId . '-undeliverable-' . preg_replace('/\W/', '', $what), $payId, 'owner_undeliverable|' . $what);
}

function mail_queue_send(string $name, string $payId, string $kind): bool
{
    if (!mail_claim('mail_queue', 'name', 'tried_at', $name)) return false;   // another request is sending it right now
    $b = booking_get($payId);
    $m = null;
    try {
        $m = $b ? queued_mail($b, $kind) : null;
        if ($m) mail_send($name, $m);
    } catch (Throwable $e) {
        log_line('MAIL_ERROR', ['pay' => $payId, 'mail' => $kind, 'error' => $e->getMessage()]);
        $q = db()->prepare('SELECT created_at FROM mail_queue WHERE name = ?');
        $q->execute([$name]);
        $old = (int)$q->fetchColumn() < time() - MAIL_GIVE_UP_DAYS * 86400;
        $q->closeCursor();
        if (!$e instanceof SmtpRejected && !$old) {
            mail_unclaim('mail_queue', 'name', 'tried_at', $name);
            return false;
        }
        db()->prepare('DELETE FROM mail_queue WHERE name = ?')->execute([$name]);
        mail_gave_up($payId, explode('|', $kind)[0], $m['to'] ?? '', $e->getMessage());
        return false;
    }
    db()->prepare('DELETE FROM mail_queue WHERE name = ?')->execute([$name]);   // sent (or nothing left to say)
    return true;
}

/* The client's confirmation (or "Změna termínu") and her "Nová rezervace": sent by one request only (mail_claim).
   True when it went. A confirmation the client's address refused for good is not retried; she is told. */
function booking_mail(string $payId, string $name, array $m, bool $owner): bool
{
    [$col, $done] = $owner ? ['owner_try', 'owner_mailed'] : ['mail_try', 'mail_sent'];
    if (!mail_claim('bookings', 'pay_id', $col, $payId, "$done = 0")) return false;
    try {
        mail_send($name, $m);
    } catch (Throwable $e) {
        log_line('MAIL_ERROR', ['pay' => $payId, 'to' => $owner ? 'owner' : 'client', 'error' => $e->getMessage()]);
        if ($e instanceof SmtpRejected) {
            booking_update($payId, $owner ? ['owner_mailed' => 1, $col => null] : ['mail_dead' => 1, $col => null]);
            if (!$owner) mail_gave_up($payId, 'confirmation', $m['to'], $e->getMessage());
        } else {
            mail_unclaim('bookings', 'pay_id', $col, $payId);
        }
        return false;
    }
    booking_update($payId, [$done => 1, $col => null]);
    return true;
}

/* --- the Meet link. Google may answer "being created" (16 Nov review): the confirmation then waits for the link
   instead of carrying a dead button. */
const MEET_WAIT_MINUTES = 30;   // after this the confirmation goes without the link; the reminders carry it
function with_meet(array $b): array
{
    if ($b['forma'] === 'osobne' || $b['meet'] || !$b['event_id']) return $b;
    try {
        $link = gcal_get($b['event_id'])['hangoutLink'] ?? null;
    } catch (Throwable $e) {
        log_line('MEET_FETCH_FAILED', ['pay' => $b['pay_id'], 'error' => $e->getMessage()]);
        return $b;
    }
    if (!$link) return $b;
    booking_update($b['pay_id'], ['meet' => $link]);
    return booking_get($b['pay_id']);
}

/* Calendar event (+ Meet) and the e-mail. A failure is recorded and retried by the safety net. */
function finish_booking(string $payId): array
{
    $b = booking_get($payId);
    $start = prague($b['slot']);
    $online = $b['forma'] !== 'osobne';
    if (!$b['event_id'] && $start->getTimestamp() <= time() && in_array($b['status'], ['pending', 'failed'], true)) {
        // Google was down until after the session's start: no event and no confirmation for a time that has passed,
        // the payment goes back as with a payment booked too late (17 Nov review, second list)
        $s = db()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = 'missed', owner_mailed = 1, updated_at = ?
            WHERE pay_id = ? AND status IN ('pending', 'failed') AND event_id IS NULL");
        $s->execute([time(), $payId]);
        if ($s->rowCount() === 0) return booking_get($payId);
        log_line('PAID_TOO_LATE', ['pay' => $payId, 'slot' => $b['slot'], 'after' => 'calendar retry']);
        return refund_double($payId);
    }
    try {
        if (!$b['event_id']) {
            $clash = (bool)gcal_busy($start, $start->modify('+50 minutes'));
            // worded like the events of Google's booking pages (owner, 16 Nov): "Terapie osobně (Jméno)", who booked,
            // what they wrote, the amount; the client is a guest in both formats, as there
            $title = (lab() ? 'TEST · ' : '') . event_title($b);
            $ev = gcal_create($start, $title, event_description($b), $online, $b['email'] ?: null, $payId);
            $meet = $ev['hangoutLink'] ?? null;
            for ($i = 0; $online && !$meet && $i < 2; $i++) {   // usually there at once; else Google needs a moment
                sleep(1);
                try { $meet = gcal_get($ev['id'])['hangoutLink'] ?? null; } catch (Throwable $e) { break; }
            }
            booking_update($payId, ['event_id' => $ev['id'], 'meet' => $meet, 'clash' => (int)$clash, 'status' => 'booked', 'error' => null]);
            log_line($clash ? 'BOOKED_CLASH' : 'BOOKED', ['pay' => $payId, 'slot' => $b['slot']]);
            $b = booking_get($payId);
        }
    } catch (Throwable $e) {
        booking_update($payId, ['status' => $b['event_id'] ? 'booked' : 'failed', 'error' => substr($e->getMessage(), 0, 500)]);
        log_line('BOOKING_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);
        return booking_get($payId);
    }
    // the two e-mails separately: one failing doesn't hold back the other; the safety net retries both
    $b = with_meet($b);
    if (!$b['mail_sent'] && $b['email'] && $online && !$b['meet'] && (int)$b['created_at'] > time() - MEET_WAIT_MINUTES * 60) {
        log_line('MEET_PENDING', ['pay' => $payId]);   // the safety net sends the confirmation once the link is there
    } elseif (!$b['mail_sent'] && $b['email'] && !$b['mail_dead']) {
        if (booking_mail($payId, $payId, mail_build($b, $start, cancel_url($payId)), false)) log_line('MAILED', ['pay' => $payId]);
    }
    if (!$b['owner_mailed']) {
        if (booking_mail($payId, $payId . '-owner', mail_owner_new($b, $start, owner_url($payId)), true)) log_line('OWNER_MAILED', ['pay' => $payId]);
    }
    return booking_get($payId);
}

/* Client cancels from the e-mail link. 36+ hours before: full refund. Later: no refund. Always: the slot frees up.
   $expectRefund: the page told the client the money comes back. If the 36 hours ran out while they were reading it,
   nothing is cancelled (16 Nov test: a click at 35:59 cancelled without the promised refund). */
function cancel_booking(string $payId, ?DateTimeImmutable $now = null, bool $expectRefund = false): array
{
    $b = booking_get($payId);
    if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
    if ($b['status'] === 'cancelled') return $b + ['already' => true, 'mailed' => false];   // nothing sent now (review 17 Nov)
    if ($b['status'] !== 'booked') throw new ClientError('Rezervace se ještě zpracovává. Stačí to zkusit za pár minut, případně zavolat na ' . PHONE . '.', 409);
    if (moving($b)) throw new ClientError('Termín se právě mění. Stačí to zkusit za chvíli.', 409);
    $now ??= new DateTimeImmutable();
    $hours = (prague($b['slot'])->getTimestamp() - $now->getTimestamp()) / 3600;
    if ($hours <= 0) throw new ClientError('Sezení už začalo nebo proběhlo, zrušit ho nejde.', 400);
    if ($expectRefund && $hours < cfg()['free_cancel_hours']) {
        throw new ClientError('Mezitím uplynula lhůta 36 hodin, platba by se už nevrátila. Termín zůstává, nic se nezměnilo.', 409);
    }
    // two decimals: 35.96 hours is never shown or kept as "36" (17 Nov review, second list)
    $fields = ['cancelled_by' => 'client', 'hours_before' => round($hours, 2)];
    if ($hours < cfg()['free_cancel_hours']) {
        // an earlier click just before the 36 hours ran out may have been refunded by Stripe with its answer lost:
        // that cancellation is finished (with the money) instead of a new one without it (17 Nov review, second list)
        try { $r = refund_reconcile($payId); } catch (Throwable $e) { $r = null; log_line('REFUND_CHECK_FAILED', ['pay' => $payId, 'error' => $e->getMessage()]); }
        if ($r && $r['status'] === 'cancelled') return cancel_answer($payId);
    } else {
        [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($payId));
        if ($code !== 200 || empty($s['payment_intent'])) throw new ClientError('Zrušení se teď nepovedlo. Stačí to zkusit znovu.', 502);
        [$code, $r] = stripe_refund($s['payment_intent'], (int)($s['amount_total'] ?? 0), 'zruseni klientem', 'cancel-' . $payId);
        if ($code !== 200 || !refund_alive($r)) {
            // the first request has cancelled it meanwhile: that is the answer, not an error
            if (($done = booking_get($payId)) && $done['status'] === 'cancelled') return cancel_answer($payId);
            log_line('REFUND_FAILED', ['pay' => $payId, 'code' => $code, 'status' => $r['status'] ?? null, 'error' => $r['error']['message'] ?? null]);
            throw new ClientError('Vrácení peněz se nepovedlo, termín zůstává. Pomůže telefon: ' . PHONE . '.', 502);
        }
        $fields += ['refund_id' => $r['id'], 'refund_status' => $r['status']];
    }
    // only one of two parallel requests gets here first, the other returns the cancelled booking without a second set of e-mails
    if (!cancel_record($payId, $fields)) {
        // the other request cancelled first. If it did so without a refund (it came after the 36 hours, this one just
        // before) but this one's refund went through, the money is on its way back: the booking says so and the client
        // gets the refund e-mail (17 Nov review: the refund vanished from the record)
        if (isset($fields['refund_id'])) {
            $r = db()->prepare('UPDATE bookings SET refund_id = ?, refund_status = ?, updated_at = ? WHERE pay_id = ? AND refund_id IS NULL');
            $r->execute([$fields['refund_id'], $fields['refund_status'], time(), $payId]);
            if ($r->rowCount() > 0) {
                log_line('REFUND_AFTER_CANCEL', ['pay' => $payId, 'refund' => $fields['refund_id']]);
                if (booking_get($payId)['email']) mail_queued($payId . '-refund-' . $fields['refund_id'], $payId, 'client_refund');
            }
        }
        return cancel_answer($payId);
    }
    log_line('CANCELLED', ['pay' => $payId, 'hours' => $fields['hours_before'], 'refund' => $fields['refund_id'] ?? null]);
    refund_watch($payId);
    mail_queued($payId . '-cancelled', $payId, 'owner_cancelled');
    // the client gets it in writing too (owner, 14 Nov): refunded or not. 'mailed': the page says it was sent only if it was
    $mailed = booking_get($payId)['email'] ? mail_queued($payId . '-client-cancelled', $payId, 'client_cancelled') : false;
    return booking_get($payId) + ['mailed' => $mailed];
}

/* For a click that came second: whether the client's cancellation e-mail has gone (17 Nov review, second list: the
   page said "sent" while it was still waiting). The first click may still be sending it: then "it follows". */
function cancel_answer(string $payId): array
{
    $b = booking_get($payId);
    $q = db()->prepare('SELECT COUNT(*) FROM mail_queue WHERE name = ?');
    $q->execute([$payId . '-client-cancelled']);
    $waiting = (int)$q->fetchColumn() > 0;
    $q->closeCursor();
    return $b + ['mailed' => $b['email'] && !$waiting];
}

/* Marks a booked session cancelled, with its refund, in one write (17 Nov review, second list: a request that died
   between two writes left a cancellation without its refund). The event is written down as stale in the same write,
   so the safety net deletes it even if this request dies before Google answers. False: it was no longer booked. */
function cancel_record(string $payId, array $fields): bool
{
    $b = booking_get($payId);
    $fields = ['status' => 'cancelled', 'stale_event' => $b['event_id'] ?? null, 'updated_at' => time()] + $fields;
    $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($fields)));
    $s = db()->prepare("UPDATE bookings SET $set WHERE pay_id = :pay_id_ AND status = 'booked'");
    $s->execute($fields + ['pay_id_' => $payId]);
    if ($s->rowCount() === 0) return false;
    if ($b['event_id']) event_gone($payId, $b['event_id']);
    return true;
}

/* Deletes the event of a booking already marked cancelled. If Google fails, the event is written down and the
   safety net deletes it later (16 Nov review: a stale event kept blocking the freed time). */
function event_gone(string $payId, string $eventId): void
{
    try {
        gcal_delete($eventId);
        booking_update($payId, ['stale_event' => null]);
    } catch (Throwable $e) {
        booking_update($payId, ['stale_event' => $eventId]);
        log_line('EVENT_DELETE_FAILED', ['pay' => $payId, 'error' => $e->getMessage()]);
    }
}

/* Stripe's refund states: pending and succeeded are on their way; failed and canceled mean no money goes back
   (16 Nov review: a refund id alone was taken as success). */
function refund_alive(array $r): bool
{
    return !empty($r['id']) && !in_array($r['status'] ?? '', ['failed', 'canceled'], true);
}

/* A recorded refund whose money is not on its way: Stripe refused or cancelled it, it waits for an action in Stripe
   (requires_action), only part of the payment went back (partial), or Stripe has no such refund (missing).
   The last three: 17 Nov review, second list. */
const REFUND_NOT_BACK = ['failed', 'canceled', 'requires_action', 'partial', 'missing'];
function refund_failed(array $b): bool
{
    return $b['refund_id'] !== null && in_array($b['refund_status'] ?? '', REFUND_NOT_BACK, true);
}

/* She hears once about a refund that is recorded but not on its way. */
function refund_watch(string $payId): void
{
    $b = booking_get($payId);
    if ($b && refund_failed($b)) mail_queued($payId . '-refund-failed-' . $b['refund_id'], $payId, 'owner_refund_failed');
}

/* Stripe reports a refund: made (also one she made in her Stripe dashboard), changed or failed later. The state is read
   from Stripe, never from the webhook body; all refunds of that payment are compared with the booking
   (refund_reconcile). Null: not a booking of this copy. */
function refund_changed(string $refundId): ?array
{
    [$code, $r] = stripe('GET', 'refunds/' . rawurlencode($refundId));
    if ($code !== 200) throw new RuntimeException('Stripe refund read failed: HTTP ' . $code);
    $b = booking_of_payment($refundId, (string)($r['payment_intent'] ?? ''));
    if (!$b) return null;                                              // another copy's refund, or an old one
    try {
        return refund_reconcile($b['pay_id']);
    } catch (Throwable $e) {
        // Stripe's list or the payment not readable now: at least this refund's own state, as before
        log_line('REFUND_CHECK_FAILED', ['pay' => $b['pay_id'], 'error' => $e->getMessage()]);
        if ($b['refund_id'] !== $refundId || in_array($b['refund_status'], ['partial', 'missing'], true)) return $b;
    }
    $was = refund_failed($b);
    booking_update($b['pay_id'], ['refund_status' => (string)($r['status'] ?? '')]);
    $b = booking_get($b['pay_id']);
    if (!$was && refund_failed($b)) {
        log_line('REFUND_FAILED_LATER', ['pay' => $b['pay_id'], 'refund' => $refundId, 'reason' => $r['failure_reason'] ?? null]);
        mail_queued($b['pay_id'] . '-refund-failed-' . $refundId, $b['pay_id'], 'owner_refund_failed');
    }
    return $b;
}

/* --- Stripe is the truth about refunds (17 Nov review, second list). The site records the refunds it makes, but money
   also goes back in other ways: she refunds in her Stripe dashboard, a refund's answer is lost, a refund succeeds and
   fails days later with the webhook lost, or she returns a payment in parts. All refunds of the payment are added up
   and the booking follows the sum. */

function payment_intent_id(array $s): ?string
{
    $pi = $s['payment_intent'] ?? null;
    return is_array($pi) ? ($pi['id'] ?? null) : ($pi ?: null);
}

/* The payment behind a booking: [payment intent, amount paid]. Kept on the booking; older bookings ask Stripe once.
   Written without touching updated_at (the safety net's order and timing go by it). */
function booking_payment(array $b): array
{
    if ($b['payment_intent'] && (int)$b['amount'] > 0) return [$b['payment_intent'], (int)$b['amount']];
    [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($b['pay_id']));
    if ($code !== 200 || !payment_intent_id($s)) throw new RuntimeException('Stripe payment read failed: HTTP ' . $code);
    $pi = payment_intent_id($s);
    $amount = (int)($s['amount_total'] ?? 0);
    db()->prepare('UPDATE bookings SET payment_intent = ?, amount = ? WHERE pay_id = ?')->execute([$pi, $amount ?: null, $b['pay_id']]);
    return [$pi, $amount];
}

/* All refunds of a payment. 'back': those whose money goes back (not failed, cancelled or waiting for an action),
   newest first; 'full': the newest of them when together they return the whole amount, else null. */
function refunds_of(string $pi, int $amount): array
{
    [$code, $list] = stripe('GET', 'refunds', ['payment_intent' => $pi, 'limit' => 100]);
    if ($code !== 200) throw new RuntimeException('Stripe refund list failed: HTTP ' . $code);
    $all = $list['data'] ?? [];
    $back = array_values(array_filter($all, fn($r) => refund_alive($r) && ($r['status'] ?? '') !== 'requires_action'));
    $total = array_sum(array_map(fn($r) => (int)($r['amount'] ?? 0), $back));
    return ['all' => $all, 'back' => $back, 'total' => $total, 'full' => $back && $amount > 0 && $total >= $amount ? $back[0] : null];
}

/* The booking a refund belongs to: by the refund the site recorded, or by the payment. */
function booking_of_payment(?string $refundId, string $pi): ?array
{
    $s = db()->prepare("SELECT pay_id FROM bookings WHERE refund_id = ? OR (payment_intent = ? AND ? <> '') ORDER BY refund_id = ? DESC LIMIT 1");
    $s->execute([$refundId, $pi, $pi, $refundId]);
    $payId = $s->fetchColumn();
    $s->closeCursor();
    return $payId ? booking_get((string)$payId) : null;
}

/* Compares the booking with all refunds of its payment and tells whoever needs to know:
   - the money is back in full, but the booking doesn't say so: recorded. A session still booked whose refund the site
     made itself (a cancel whose answer was lost) is cancelled as that request meant; one refunded in her dashboard
     stays booked and she gets an e-mail with a button to cancel it. A cancelled one: the client hears the money comes.
   - the booking says refunded, but Stripe returns less (a part failed, or a success failed later): she gets an e-mail.
   Null: no such booking. Throws when Stripe can't be read (the safety net asks again). */
function refund_reconcile(string $payId): ?array
{
    $b = booking_get($payId);
    if (!$b || $b['status'] === 'pending') return $b;   // being booked right now: book_paid and the retry look at it
    [$pi, $amount] = booking_payment($b);
    $r = refunds_of($pi, $amount);
    $was = $b['refund_id'] !== null && !refund_failed($b);   // the booking says the money goes back
    if ($r['full']) {
        // the site's own refund stands for the sum while it is one of them; else the newest
        $own = current(array_filter($r['back'], fn($x) => $x['id'] === $b['refund_id'])) ?: $r['full'];
        $fields = ['refund_id' => $own['id'], 'refund_status' => (string)$own['status']];
    } elseif ($b['refund_id'] === null) {
        return $b;   // nothing (or only a part) returned, and the booking never said otherwise
    } else {
        $own = current(array_filter($r['all'], fn($x) => ($x['id'] ?? '') === $b['refund_id']));
        $st = $own ? (string)($own['status'] ?? '') : 'missing';
        $fields = ['refund_status' => in_array($st, REFUND_NOT_BACK, true) ? $st : 'partial'];
    }
    if (array_intersect_key($b, $fields) == $fields) return $b;   // nothing new
    booking_update($payId, $fields);
    $now = booking_get($payId);
    $back = !refund_failed($now);
    log_line($back ? 'REFUND_FOUND' : 'REFUND_NOT_BACK', ['pay' => $payId, 'refund' => $now['refund_id'], 'status' => $now['refund_status'], 'total' => $r['total']]);
    if (!$back) {
        if ($was) refund_watch($payId);
        return $now;
    }
    if ($was) return $now;   // still on its way, only its state changed
    $start = prague($now['slot']);
    if (in_array($now['status'], ['booked', 'failed'], true)) {
        // why the money went back: the site writes the reason into each refund it makes
        $why = array_column(array_map(fn($x) => $x['metadata'] ?? [], $r['back']), 'duvod');
        $by = in_array('zruseni klientem', $why, true) ? 'client' : (in_array('zruseno terapeutkou', $why, true) ? 'owner' : null);
        if ($now['status'] === 'failed') {
            // paid, the calendar entry never made, and returned meanwhile: never booked
            $s = db()->prepare("UPDATE bookings SET status = 'cancelled', cancelled_by = 'refunded', owner_mailed = 1, updated_at = ? WHERE pay_id = ? AND status = 'failed'");
            $s->execute([time(), $payId]);
            if ($s->rowCount()) mail_queued($payId . '-refund-found', $payId, 'owner_refund_found');
        } elseif ($by && cancel_record($payId, ['cancelled_by' => $by,
                'hours_before' => round(($start->getTimestamp() - (int)($r['full']['created'] ?? time())) / 3600, 2)])) {
            // a cancellation whose request died after Stripe refunded: finished now, the same e-mails as it would have sent
            log_line($by === 'client' ? 'CANCELLED' : 'OWNER_CANCELLED', ['pay' => $payId, 'refund' => $now['refund_id'], 'found' => true]);
            if ($by === 'client') mail_queued($payId . '-cancelled', $payId, 'owner_cancelled');
            if ($now['email']) mail_queued($payId . ($by === 'client' ? '-client-cancelled' : '-cancel'), $payId, $by === 'client' ? 'client_cancelled' : 'client_cancel');
        } elseif (!$by) {
            mail_queued($payId . '-refund-found', $payId, 'owner_refund_found');   // she decides whether it still takes place
        }
    } elseif ($now['status'] === 'cancelled') {
        if (in_array($now['cancelled_by'], ['double', 'missed'], true)) double_mails($payId);
        elseif ($now['email'] && $now['cancelled_by'] !== 'refunded') {
            // deleted by her in the calendar: the client has heard nothing yet, so "I had to cancel" (as owner_cancel)
            mail_queued($payId . '-refund-' . $now['refund_id'], $payId, $now['cancelled_by'] === 'calendar' ? 'client_cancel' : 'client_refund');
        }
    }
    return booking_get($payId);
}

/* A move is talking to Google right now. */
function moving(array $b, ?int $now = null): bool
{
    return (int)($b['lock_until'] ?? 0) > ($now ?? time());
}

/* One full refund. The idempotency key makes a double click, two tabs or her phone and laptop refund only once, as
   long as every try sends the same parameters (16 Nov review: the hours in the reason changed between tries, Stripe
   refused the retry, and a refund that had gone through was reported as failed).
   $amount: what the client paid (the session's amount_total), so a refund found on Stripe counts only if it is the
   whole payment (17 Nov review: an earlier partial refund from her Stripe dashboard was taken as the full one). */
function stripe_refund(string $paymentIntent, int $amount, string $why, string $key, ?string $failed = null): array
{
    $send = fn() => stripe('POST', 'refunds', ['payment_intent' => $paymentIntent, 'reason' => 'requested_by_customer',
        'metadata' => ['duvod' => $why]], $key);
    try {
        [$code, $r] = $send();
        // two requests at once: Stripe refuses the second while the first runs (409); a moment later the same key
        // gives back the first one's refund
        if ($code === 409) {
            sleep(2);
            [$code, $r] = $send();
        }
    } catch (RuntimeException $e) {
        [$code, $r] = [0, ['error' => ['message' => $e->getMessage()]]];   // no answer: Stripe may have made it anyway
    }
    if ($code === 200) return [$code, $r];
    // no answer, or a refusal: a full refund Stripe already made for this payment is the answer (not the one that
    // failed, and not a partial one: the rest of the money would never go back)
    try {
        [$lc, $list] = stripe('GET', 'refunds', ['payment_intent' => $paymentIntent, 'limit' => 10]);
    } catch (RuntimeException $e) {
        return [$code, $r];
    }
    // the whole payment back, in one refund or in several (she may have refunded it in parts in her Stripe dashboard;
    // 17 Nov review, second round): the newest one stands for them. Stripe lists newest first.
    $alive = array_values(array_filter($lc === 200 ? ($list['data'] ?? []) : [], fn($x) => ($x['id'] ?? '') !== $failed && refund_alive($x)));
    $total = array_sum(array_map(fn($x) => (int)($x['amount'] ?? 0), $alive));
    if ($amount > 0 && $alive && $total >= $amount) {
        log_line('REFUND_FOUND', ['refund' => $alive[0]['id'], 'code' => $code, 'refunds' => count($alive), 'total' => $total]);
        return [200, $alive[0]];
    }
    return [$code, $r];
}

/* Full refund through Stripe (her cancel, her refund after a cancellation, a double payment). */
function refund_full(string $payId, string $why, ?string $failed = null): array
{
    [$code, $s] = stripe('GET', 'checkout/sessions/' . rawurlencode($payId));
    if ($code !== 200 || empty($s['payment_intent'])) throw new ClientError('Platbu se teď nepodařilo najít. Zkus to prosím znovu.', 502);
    // after a failed refund the same key would only give back the same failed refund
    $key = 'cancel-' . $payId . ($failed ? '-after-' . $failed : '');
    [$code, $r] = stripe_refund($s['payment_intent'], (int)($s['amount_total'] ?? 0), $why, $key, $failed);
    if ($code !== 200) {
        log_line('REFUND_FAILED', ['pay' => $payId, 'code' => $code, 'error' => $r['error']['message'] ?? null]);
        throw new ClientError('Vrácení peněz se nepovedlo, nic se nezměnilo. Zkus to prosím znovu.', 502);
    }
    if (!refund_alive($r)) {
        log_line('REFUND_FAILED', ['pay' => $payId, 'status' => $r['status'] ?? null, 'reason' => $r['failure_reason'] ?? null]);
        throw new ClientError('Stripe vrácení peněz odmítl (' . ($r['failure_reason'] ?? $r['status'] ?? '?') . '). Nic se nezměnilo; vrácení je potřeba vyřešit v Stripe.', 502);
    }
    return ['refund_id' => $r['id'], 'refund_status' => $r['status']];
}

/* The owner cancels (any time, always a full refund), or refunds a session the client cancelled late. The client gets an e-mail. */
function owner_cancel(string $payId, ?DateTimeImmutable $now = null): array
{
    $b = booking_get($payId);
    if (!$b) throw new ClientError('Rezervace nenalezena.', 404);
    $failed = refund_failed($b) ? $b['refund_id'] : null;            // Stripe refused the refund before: she may try again
    // refunded already, but the session is still booked (she refunded it in her Stripe dashboard): only cancelled now,
    // never refunded twice (17 Nov review, second list)
    $paidBack = $b['refund_id'] && !$failed;
    if ($paidBack && $b['status'] !== 'booked') return $b + ['already' => true, 'mailed' => false];   // nothing more to do
    if (in_array($b['status'], ['pending', 'failed'], true)) throw new ClientError('Rezervace se ještě zpracovává. Zkus to prosím za pár minut.', 409);
    if (moving($b)) throw new ClientError('Termín se právě přesouvá. Zkus to prosím za chvíli.', 409);
    $refundOnly = $b['status'] === 'cancelled';
    $now ??= new DateTimeImmutable();
    // owner, 16 Nov: once the session has taken place, the money is not returned
    // (also an event she deleted from her calendar only after it started)
    if (!$refundOnly ? prague($b['slot']) <= $now : ($b['hours_before'] !== null && (float)$b['hours_before'] <= 0)) {
        throw new ClientError('Sezení už proběhlo, platba se nevrací.', 409);
    }
    $fields = $paidBack ? [] : refund_full($payId, $refundOnly ? 'vraceno terapeutkou po zruseni' : 'zruseno terapeutkou', $failed);
    // the refund and the cancellation in one write (17 Nov review, second list); only one of two clicks gets it
    $setRefund = function () use ($payId, $fields, $failed): bool {
        $s = db()->prepare('UPDATE bookings SET refund_id = ?, refund_status = ?, updated_at = ? WHERE pay_id = ? AND refund_id IS ?');
        $s->execute([$fields['refund_id'], $fields['refund_status'], time(), $payId, $failed]);
        return $s->rowCount() > 0;
    };
    if ($refundOnly) {
        if (!$setRefund()) return booking_get($payId);   // the other request got there first
    } elseif (!cancel_record($payId, $fields + ['cancelled_by' => 'owner',
            'hours_before' => round((prague($b['slot'])->getTimestamp() - $now->getTimestamp()) / 3600, 2)])) {
        // no longer booked: the other click, or the client cancelling late at the same moment. This refund went
        // through, so the booking keeps it and the client hears about it
        if ($fields && $setRefund() && booking_get($payId)['email']) mail_queued($payId . '-refund-' . $fields['refund_id'], $payId, 'client_refund');
        return booking_get($payId);
    }
    log_line('OWNER_CANCELLED', ['pay' => $payId, 'refund' => $fields['refund_id'] ?? $b['refund_id'], 'refund_only' => $refundOnly]);
    refund_watch($payId);
    $b = booking_get($payId);
    // deleted by her in the calendar: the client hasn't heard anything yet, so "I had to cancel, refunding"
    $mailed = $b['email'] ? mail_queued($payId . '-cancel' . ($failed ? '-' . ($fields['refund_id'] ?? '') : ''), $payId,
        $refundOnly && $b['cancelled_by'] !== 'calendar' ? 'client_refund' : 'client_cancel') : false;
    return $b + ['mailed' => $mailed];
}

/* A move to another free time of the same format. The calendar event (and its Meet link) moves with it;
   the client gets "Změna termínu" with a new .ics. No new payment, the client's link stays the same.
   The client (decision 14 Nov): only to a free time, as often as they like, but only while a free
   cancel is still possible (36+ hours before the time booked now); later it is a cancellation. She gets
   an e-mail every time, with the count, so a client who keeps postponing stands out.
   The owner (16 Nov): her hours are the baseline, not a limit. Any whole hour OWNER_MOVE_FIRST..LAST within the
   booking window, outside her hours too (as a drag in Google Calendar can), but never onto another session
   (any format, within an hour either side) or a time someone is paying for right now. */
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
    if ($client) {
        if (!in_array($new->format('G:i'), free_slots($new)[$b['forma']][$new->format('Y-m-d')] ?? [], true)) throw new ClientError($taken, 409);
    } else {
        $h = (int)$new->format('G');
        if ($new->format('i') !== '00' || $h < OWNER_MOVE_FIRST || $h > OWNER_MOVE_LAST) throw new ClientError('Přesunout jde na celou hodinu mezi ' . OWNER_MOVE_FIRST . ':00 a ' . OWNER_MOVE_LAST . ':00.', 400);
        if ($new <= $now) throw new ClientError('Tenhle čas už proběhl.', 400);
        if ($new > slot_window($now)[1]) throw new ClientError('Tak daleko dopředu se zatím rezervovat nedá.', 400);
    }
    $slot = $new->format(DATE_ATOM);
    $at = $new->getTimestamp();
    db_tx(function (PDO $pdo) use ($slot, $at, $payId, $taken, $client, $b) {
        $s = $pdo->prepare("SELECT (SELECT COUNT(*) FROM holds WHERE slot = :s AND expires_at >= :now)
            + (SELECT COUNT(*) FROM bookings WHERE slot = :s AND status IN ('pending','booked','failed') AND pay_id != :p)");
        $s->execute(['s' => $slot, 'now' => time(), 'p' => $payId]);
        if ((int)$s->fetchColumn() > 0) throw new ClientError($taken, 409);
        // a session moved off the hour (in Google Calendar, 18:30) still blocks the hours it touches
        $o = $pdo->prepare("SELECT slot FROM bookings WHERE status IN ('pending','booked','failed') AND pay_id != ? AND slot LIKE ?");
        $o->execute([$payId, substr($slot, 0, 10) . '%']);
        foreach ($o->fetchAll(PDO::FETCH_COLUMN) as $other) {
            if (abs(prague($other)->getTimestamp() - $at) < 3600) throw new ClientError($taken, 409);
        }
        // only from the time this request read, and only when no other move is talking to Google (16 Nov review:
        // two moves at once left Google on one time and the booking on another). The lock covers the Google call.
        $u = $pdo->prepare("UPDATE bookings SET slot = ?, moves = moves + 1, client_moves = client_moves + ?, mail_sent = 0, clash = 0, reminded = 0, moved_at = ?, updated_at = ?, lock_until = ?
            WHERE pay_id = ? AND slot = ? AND status = 'booked' AND (lock_until IS NULL OR lock_until < ?)");
        $u->execute([$slot, $client ? 1 : 0, time(), time(), time() + 60, $payId, $b['slot'], time()]);
        if ($u->rowCount() === 0) throw new ClientError($client ? 'Termín se mezitím změnil. Načtěte prosím stránku znovu.' : 'Termín se mezitím změnil. Načti prosím stránku znovu.', 409);
    });
    try {
        if ($b['event_id']) gcal_move($b['event_id'], $new);
    } catch (Throwable $e) {
        if (move_landed($payId, $b, $new)) {
            log_line('MOVE_LANDED', ['pay' => $payId, 'by' => $by, 'error' => $e->getMessage()]);   // Google moved it; its answer got lost
            db()->prepare('UPDATE bookings SET lock_until = NULL WHERE pay_id = ?')->execute([$payId]);
            log_line('MOVED', ['pay' => $payId, 'by' => $by, 'from' => $b['slot'], 'to' => $slot]);
            return moved_mails(booking_get($payId), $old, $by);
        }
        // back to the time before, only if the booking is still where this move put it
        db()->prepare('UPDATE bookings SET slot = ?, moves = ?, client_moves = ?, mail_sent = ?, reminded = ?, moved_at = ?, lock_until = NULL, updated_at = ? WHERE pay_id = ? AND slot = ?')
            ->execute([$b['slot'], $b['moves'], $b['client_moves'], $b['mail_sent'], $b['reminded'], $b['moved_at'], time(), $payId, $slot]);
        log_line('MOVE_FAILED', ['pay' => $payId, 'by' => $by, 'error' => $e->getMessage()]);
        throw new ClientError($client ? 'Termín se teď nepodařilo změnit, platí ten původní. Stačí to zkusit znovu.'
            : 'Kalendář se teď nepodařilo upravit, termín zůstává původní. Zkus to prosím znovu.', 502);
    }
    db()->prepare('UPDATE bookings SET lock_until = NULL WHERE pay_id = ?')->execute([$payId]);
    log_line('MOVED', ['pay' => $payId, 'by' => $by, 'from' => $b['slot'], 'to' => $slot]);
    return moved_mails(booking_get($payId), $old, $by);
}

/* After any move (her button, the client's, or her drag in Google Calendar): the client's "Změna termínu";
   if the client moved, also a note to her. */
function moved_mails(array $b, DateTimeImmutable $old, string $by): array
{
    $payId = $b['pay_id'];
    $new = prague($b['slot']);
    $b = with_meet($b);
    // if this fails, the safety net sends a normal confirmation of the new time
    if ($b['email'] && !$b['mail_dead']) booking_mail($payId, $payId . '-move-' . $b['moves'], mail_build($b, $new, cancel_url($payId), $old, $by === 'client'), false);
    // queued, so a failure is retried (review 17 Nov: it was lost)
    if ($by === 'client') mail_queued($payId . '-moved-' . $b['moves'], $payId, 'owner_moved|' . $old->format(DATE_ATOM));
    return booking_get($payId);
}

/* Google answered a move with an error (or not at all): did the event move anyway? (17 Nov review: Google moved it,
   its answer was lost, and the booking went back to the old time after someone else had paid for that time.)
   Google's own event decides. If Google can't be read either: the old time already booked, or being paid for, by
   someone else means it was free in her calendar, so the event did move (17 Nov review, second round: a payment
   still on Stripe's page was not counted); otherwise it is taken as not moved, and if it did move after all, the
   calendar sync moves the booking to it within a minute. */
function move_landed(string $payId, array $b, DateTimeImmutable $new): bool
{
    if (!$b['event_id']) return false;
    try {
        $at = gcal_get($b['event_id'])['start']['dateTime'] ?? null;
        return $at !== null && (new DateTimeImmutable($at))->getTimestamp() === $new->getTimestamp();
    } catch (Throwable $e) {
        $t = db()->prepare("SELECT (SELECT COUNT(*) FROM bookings WHERE slot = :s AND status IN ('pending','booked','failed') AND pay_id != :p)
            + (SELECT COUNT(*) FROM holds WHERE slot = :s AND expires_at >= :now)");
        $t->execute(['s' => $b['slot'], 'p' => $payId, 'now' => time()]);
        return (int)$t->fetchColumn() > 0;
    }
}

const OWNER_MOVE_FIRST = 7;    // her moves from the hours page: 7:00 ... 20:00 (owner, 16 Nov)
const OWNER_MOVE_LAST = 20;

function owner_move(string $payId, string $den, string $cas, ?DateTimeImmutable $now = null): array
{
    return move_booking($payId, $den, $cas, 'owner', $now);
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
    $next = $now;   // where the next run starts looking: never past an event this run had to leave for later
    foreach ($events as $ev) {
        $find->execute([(string)($ev['id'] ?? '')]);
        $b = $find->fetch();
        $find->closeCursor();   // before the writes below (see owner_login)
        if (!$b) continue;                                            // not ours, or already cancelled through the site
        if ($b['updated_at'] > $now - 30 || moving($b, $now)) {
            // the site is changing it right now; the next run looks again, even if that run comes late
            // (16 Nov review: an event left for later could fall behind the cursor and never be read again)
            $next = min($next, (int)(strtotime((string)($ev['updated'] ?? '')) ?: $now - 60));
            continue;
        }
        $payId = $b['pay_id'];
        $old = prague($b['slot']);
        if (($ev['status'] ?? '') === 'cancelled') {
            // when she deleted it (Google's time of the change), not when this run noticed: a run that comes late,
            // after the session's start, must not block her refund (16 Nov review)
            $deleted = min($now, (int)(strtotime((string)($ev['updated'] ?? '')) ?: $now));
            booking_update($payId, ['status' => 'cancelled', 'cancelled_by' => 'calendar',
                'hours_before' => round(($old->getTimestamp() - $deleted) / 3600, 2)]);   // 2 places: a minute before is not 0
            log_line('DELETED_IN_CALENDAR', ['pay' => $payId]);
            // queued (review 17 Nov: when this one e-mail failed, a paid client was left cancelled, unrefunded and untold)
            mail_queued($payId . '-deleted', $payId, 'owner_deleted');
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
                // queued: a failed e-mail was retried only if Google listed the event again, which it need not
                booking_update($payId, ['declined_mailed' => 1]);
                mail_queued($payId . '-declined', $payId, 'owner_declined');
                $b = booking_get($payId);
                log_line('GUEST_DECLINED', ['pay' => $payId]);
                $done['declined'][] = $payId;
            }
        }
    }
    state_set('calendar_sync', (string)$next);
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
        'cancelled' => $b['status'] === 'cancelled', 'refunded' => (bool)$b['refund_id'] && !refund_failed($b),
        'refund_failed' => refund_failed($b), 'mailed' => (bool)($b['mail_sent'] ?? 0),
        // who cancelled: the cancel page says why no money came back (review 17 Nov: it always blamed the 36 hours)
        'cancelled_by' => $b['status'] === 'cancelled' ? ($b['cancelled_by'] ?: 'client') : null,
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
            if ($kind) mail_send($b['pay_id'] . '-remind-' . $kind . '-' . $b['moves'], mail_reminder(with_meet($b), $start, $kind, $now));
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
    $paused = mail_paused();   // over the mailbox's limit: reminders and unsent e-mails wait (they go once it ends)
    if (!$paused && ($r = send_reminders())) $done['reminded'] = $r;
    // paid sessions nobody booked. A Stripe failure here is reported at the end: the retries below don't need Stripe's
    // list and must not wait for it (16 Nov review: one failed list stopped them all)
    $stripeError = null;
    try {
        foreach (paid_sessions_recent() as $s) {
            // only this copy's payments: the test site and the lab share one Stripe test account (14 Nov: the test site's
            // first run booked the lab's payments again)
            if (($s['payment_status'] ?? '') === 'paid' && ($s['metadata']['zdroj'] ?? '') === 'web'
                && session_copy($s) === site_copy() && !booking_get($s['id'])) {
                book_paid($s);
                $done['booked'][] = $s['id'];
            }
        }
    } catch (Throwable $e) {
        $stripeError = $e;
        log_line('SWEEP_STRIPE_ERROR', ['error' => $e->getMessage()]);
    }
    // cancelled sessions whose calendar event Google did not delete: the time stays blocked until it goes
    foreach (db()->query('SELECT pay_id, stale_event FROM bookings WHERE stale_event IS NOT NULL')->fetchAll() as $row) {
        event_gone($row['pay_id'], $row['stale_event']);
        if (!booking_get($row['pay_id'])['stale_event']) $done['events_deleted'][] = $row['pay_id'];
    }
    // refunds: those still on their way, and every booking whose payment Stripe refunded in the last days in a way
    // the booking doesn't show (her dashboard, a lost answer, a success that failed later with its webhook lost).
    // A failure reaches her even without the webhook (17 Nov review, both lists)
    try {
        $check = refunds_to_check();
        $check = array_merge($check, refunds_unmatched(REFUND_CHECK_MAX - count($check)));
    } catch (Throwable $e) {
        $stripeError ??= $e;
        log_line('SWEEP_STRIPE_ERROR', ['error' => $e->getMessage()]);
    }
    foreach (array_unique($check ?? []) as $payId) {
        try {
            $was = refund_failed(booking_get($payId));
            $b = refund_reconcile($payId);
            if ($b && !$was && refund_failed($b)) $done['refund_failed'][] = $payId;
            elseif ($b && $b['refund_id'] && !refund_failed($b)) $done['refund_checked'][] = $payId;
        } catch (Throwable $e) {
            log_line('SWEEP_REFUND_ERROR', ['pay' => $payId, 'error' => $e->getMessage()]);   // asked again next run
        }
    }
    // a payment for a time already taken whose refund did not go through yet
    foreach (db()->query("SELECT pay_id FROM bookings WHERE cancelled_by IN ('double', 'missed') AND refund_id IS NULL")->fetchAll(PDO::FETCH_COLUMN) as $payId) {
        $done['double_refunded'][] = refund_double($payId)['refund_id'] ? $payId : $payId . ' (still not)';
    }
    if (!$paused) {
        foreach (db()->query('SELECT name, pay_id, kind FROM mail_queue ORDER BY created_at')->fetchAll() as $m) {
            if (mail_queue_send($m['name'], $m['pay_id'], $m['kind'])) $done['queued_sent'][] = $m['name'];
        }
    }
    $s = db()->prepare("SELECT pay_id, status FROM bookings WHERE status = 'failed' OR (status = 'pending' AND updated_at < ?)
        OR (status = 'booked' AND mail_sent = 0 AND email IS NOT NULL AND COALESCE(mail_dead, 0) = 0) OR (status = 'booked' AND owner_mailed = 0)");
    $s->execute([time() - 300]);
    foreach ($s->fetchAll() as $row) {
        if ($paused && $row['status'] === 'booked') { $done['mail_waiting'][] = $row['pay_id']; continue; }
        $b = finish_booking($row['pay_id']);
        if ($row['status'] !== 'booked') $done['retried'][] = $row['pay_id'];
        elseif (($b['mail_sent'] || !$b['email']) && $b['owner_mailed']) $done['mailed'][] = $row['pay_id'];
        else $done['mail_failed'][] = $row['pay_id'];   // stays unsent: the next run tries again
    }
    if (array_filter(array_diff_key($done, ['mail_waiting' => 1]))) log_line('SWEEP', $done);
    if ($stripeError) throw $stripeError;   // the rest is done; the cron's output still shows the failure
    return $done;
}

/* Bookings with a refund Stripe has not finished (pending, or waiting for an action): the oldest check first. */
const REFUND_CHECK_MAX = 20;   // bookings compared with Stripe per run (each is one or two Stripe calls)
function refunds_to_check(): array
{
    $s = db()->query("SELECT pay_id FROM bookings WHERE refund_id IS NOT NULL
        AND COALESCE(refund_status, '') NOT IN ('succeeded', 'failed', 'canceled', 'partial', 'missing') ORDER BY updated_at LIMIT " . REFUND_CHECK_MAX);
    return $s->fetchAll(PDO::FETCH_COLUMN);
}

/* Bookings with a refund made in the last SWEEP_LOOKBACK_DAYS that they don't show as it is: not recorded at all, or
   recorded in another state. First a few older bookings get their payment id, so refunds of them can be matched. */
function refunds_unmatched(int $max): array
{
    if ($max <= 0) return [];
    foreach (db()->query("SELECT * FROM bookings WHERE payment_intent IS NULL AND status <> 'pending' ORDER BY slot DESC LIMIT 5")->fetchAll() as $b) {
        try { booking_payment($b); } catch (Throwable $e) { break; }   // Stripe down: the next run
    }
    [$code, $list] = stripe('GET', 'refunds', ['limit' => 100, 'created' => ['gte' => time() - SWEEP_LOOKBACK_DAYS * 86400]]);
    if ($code !== 200) throw new RuntimeException('Stripe refund list failed: HTTP ' . $code);
    $out = [];
    foreach ($list['data'] ?? [] as $r) {
        $b = booking_of_payment($r['id'] ?? null, (string)($r['payment_intent'] ?? ''));
        if (!$b || $b['status'] === 'pending') continue;   // another copy's payment, or being booked right now
        $same = $b['refund_id'] === ($r['id'] ?? null) && $b['refund_status'] === ($r['status'] ?? null);
        // a refund the booking already counts in full needs no new look (its own state is checked above when pending)
        if ($same || ($b['refund_id'] && !refund_failed($b) && $b['refund_id'] !== ($r['id'] ?? null) && refund_alive($r))) continue;
        $out[$b['pay_id']] = true;
        if (count($out) >= $max) break;
    }
    return array_keys($out);
}

/* Every completed Checkout Session of the last 3 days, all pages of Stripe's list (16 Nov review: only the first
   100 were read). Stripe lists newest first; a page ends with has_more and the next starts after its last id.
   7 days: longer than Stripe retries a webhook (3 days), so even a server down for most of a week books what was paid
   meanwhile (17 Nov review, both lists). Still inside BOOK_PAID_MAX_AGE_DAYS. */
const SWEEP_LOOKBACK_DAYS = 7;
function paid_sessions_recent(): array
{
    $out = [];
    $q = ['status' => 'complete', 'limit' => 100, 'created' => ['gte' => time() - SWEEP_LOOKBACK_DAYS * 86400]];
    for ($i = 0; $i < 50; $i++) {   // 5000 payments a day is far beyond this practice; a bound so a bad answer can't loop
        [$code, $page] = stripe('GET', 'checkout/sessions', $q);
        if ($code !== 200) throw new RuntimeException('Stripe list failed: HTTP ' . $code);
        $data = $page['data'] ?? [];
        array_push($out, ...$data);
        if (empty($page['has_more']) || !$data) break;
        $q['starting_after'] = end($data)['id'];
    }
    return $out;
}

/* Which copy of the site a payment belongs to. Payments made before the copies were marked came from the lab ("khg").
   The webhook and the safety net decide the same way (16 Nov review: they disagreed about unmarked payments). */
function session_copy(array $s): string
{
    return (string)($s['metadata']['kopie'] ?? 'khg');
}
