<?php
/* Offline tests (no Stripe, no Google): php booking/tests/run.php
   Runs against a throwaway config dir with a dummy test key, so nothing real is touched. */
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/khg-booking-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700);
file_put_contents("$tmp/stripe-test.env", "STRIPE_SECRET_KEY=sk_test_dummy\nSTRIPE_PUBLISHABLE_KEY=pk_test_dummy\n");
file_put_contents("$tmp/booking-secret", str_repeat('ab', 32));
file_put_contents("$tmp/owner-password", password_hash('spravne-heslo-42', PASSWORD_DEFAULT));
file_put_contents("$tmp/booking.env", "SITE_URL=http://127.0.0.1:8820\nSTRIPE_WEBHOOK_SECRET=whsec_test123\n");
putenv("KHG_CONFIG_DIR=$tmp");

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/stripe.php';
require __DIR__ . '/../src/google.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/mail.php';
require __DIR__ . '/../src/booking.php';
require __DIR__ . '/../src/slots.php';
require __DIR__ . '/../src/hours.php';
require __DIR__ . '/../src/owner.php';

$fails = 0;
$n = 0;
function ok(bool $cond, string $what): void
{
    global $fails, $n;
    $n++;
    if (!$cond) { $fails++; echo "FAIL  $what\n"; } else echo "ok    $what\n";
}
function throws(callable $fn, int $code, string $what): void
{
    try { $fn(); ok(false, "$what (no error)"); }
    catch (ClientError $e) { ok($e->getCode() === $code, "$what -> $code ({$e->getMessage()})"); }
}

// --- times
$w = slot_start('2026-12-22', '13:00');
ok($w->format(DATE_ATOM) === '2026-12-22T13:00:00+01:00', 'winter slot is +01:00');
ok(slot_start('2027-07-15', '9:00')->format(DATE_ATOM) === '2027-07-15T09:00:00+02:00', 'summer slot is +02:00');
$spring = slot_start('2027-03-29', '9:00');   // clock change on Sun 28 Mar 2027
ok(($spring->getTimestamp() - prague('2027-03-27T21:00:00+01:00')->getTimestamp()) / 3600 == 35.0, 'across the spring change it counts real hours (35, not 36)');
throws(fn() => slot_start('2026-02-30', '9:00'), 400, 'impossible date refused');
throws(fn() => slot_start('2026-12-22', '25:00'), 400, 'impossible time refused');
throws(fn() => slot_start('22.12.2026', '9:00'), 400, 'wrong date format refused');

// --- the client's choice
throws(fn() => parse_choice(['f' => 'online', 'date' => '2020-01-01', 'time' => '9:00', 'email' => 'a@b.cz']), 400, 'past slot refused');
throws(fn() => parse_choice(['f' => 'online', 'date' => (new DateTimeImmutable('+200 days'))->format('Y-m-d'), 'time' => '9:00', 'email' => 'a@b.cz']), 400, 'slot too far ahead refused');
$future = (new DateTimeImmutable('+10 days'))->format('Y-m-d');
throws(fn() => parse_choice(['f' => 'online', 'date' => $future, 'time' => '9:00', 'email' => '']), 400, 'online without e-mail refused');
throws(fn() => parse_choice(['f' => 'online', 'date' => $future, 'time' => '9:00', 'email' => 'nope']), 400, 'bad e-mail refused');
[$s, $meta] = parse_choice(['f' => 'osobne', 'date' => $future, 'time' => '9:00', 'phone' => '+420 777 123 456', 'price' => 1]);
ok($meta['forma'] === 'osobne' && $meta['cas'] === '9:00' && !isset($meta['price']), 'in person without e-mail accepted, price from the browser ignored');
[, $meta] = parse_choice(['f' => 'hack', 'date' => $future, 'time' => '9:00', 'email' => 'a@b.cz']);
ok($meta['forma'] === 'online', 'unknown format falls back to online');

// --- signed cancel links
$t = cancel_token('cs_test_abc');
ok(strlen($t) === 24 && !preg_match('/[+\/=]/', $t), 'token is 24 URL-safe characters');
ok(token_ok('cs_test_abc', $t), 'right token accepted');
ok(!token_ok('cs_test_abd', $t), 'token of another booking refused');
ok(!token_ok('cs_test_abc', substr($t, 0, -1) . 'X'), 'changed token refused');
ok(!token_ok('', ''), 'empty refused');
ok(str_starts_with(cancel_url('cs_test_abc'), 'http://127.0.0.1:8820/rezervace/zruseni.html?b=cs_test_abc&t='), 'cancel URL');

// --- Stripe helpers
ok(stripe_form(['a' => true, 'b' => ['c' => false, 'd' => [['e' => 1]]]]) === 'a=true&b%5Bc%5D=false&b%5Bd%5D%5B0%5D%5Be%5D=1', 'form encoding: nesting and booleans');
$payload = '{"type":"checkout.session.completed"}';
$now = 1800000000;
$sig = 't=' . $now . ',v1=' . hash_hmac('sha256', $now . '.' . $payload, 'whsec_test123');
ok(stripe_signature_ok($payload, $sig, 'whsec_test123', 300, $now), 'webhook: valid signature');
ok(!stripe_signature_ok($payload . ' ', $sig, 'whsec_test123', 300, $now), 'webhook: changed body refused');
ok(!stripe_signature_ok($payload, $sig, 'whsec_other', 300, $now), 'webhook: other secret refused');
ok(!stripe_signature_ok($payload, $sig, 'whsec_test123', 300, $now + 301), 'webhook: replay after 5 minutes refused');
ok(!stripe_signature_ok($payload, '', 'whsec_test123', 300, $now), 'webhook: missing header refused');
ok(!stripe_signature_ok($payload, $sig, '', 300, $now), 'webhook: no secret configured refused');

// --- Czech texts
ok(cz_at($w) === 'v úterý 22. prosince v 13:00', 'cz_at');
ok(cz_until($w->modify('-36 hours')) === 'pondělí 21. prosince 1:00', 'cz_until (genitive day, no "v")');
ok(cz_until(slot_start('2027-07-15', '9:00')->modify('-36 hours')) === 'úterý 13. července 21:00', 'cz_until summer');
ok(cz_day(slot_start('2026-12-24', '9:00')) === 'Čtvrtek 24. prosince 2026', 'cz_day capitalises Č');
ok(cz_label('online', $w) === 'Sezení online · úterý 22. prosince · 13:00–13:50', 'Stripe line item');

// --- e-mail
$b = ['pay_id' => 'cs_test_abc', 'forma' => 'online', 'email' => 'jana@example.com', 'meet' => 'https://meet.google.com/abc-defg-hij'];
$m = mail_build($b, $w, cancel_url('cs_test_abc'));
ok($m['subject'] === 'Potvrzení: sezení v úterý 22. prosince v 13:00', 'subject');
ok(str_contains($m['text'], 'zrušit lze nejpozději 36 hodin před sezením. Později se platba nevrací.') && !str_contains($m['text'], 'prosince 1:00'), 'text: 36 hours, no computed date');
ok(!preg_match('/Kdybyste|Těším se|Dobrý den/u', $m['text'] . $m['html']), 'no greetings or filler lines');
foreach ([$m, mail_client_cancelled($b, $w, false), mail_client_cancelled($b, $w, true)] as $x) ok(!preg_match('/vracím|musím|omlouvám|můžete|jste |Vracím/u', $x['text'] . $x['html']), 'passive wording: ' . $x['subject']);
ok(str_contains($m['html'], 'cid:' . BRAND_CID) && str_contains($m['html'], '#E8EFF4'), 'html: brand image and the online (sky) panel');
$eml = mail_eml($m);
ok(str_contains($eml, 'multipart/related') && str_contains($eml, 'Content-ID: <' . BRAND_CID . '>') && strlen(brand_png()) > 1000, 'the brand image travels inside the e-mail');
ok(str_contains($m['html'], 'Připojit se k videohovoru') && str_contains($m['html'], 'white-space:nowrap">13:00–13:50'), 'html: Meet button, times kept together');
ok(str_contains($m['html'], 'background:#E8927C;border:1.5px solid #E8927C;color:#163A55">Připojit se k videohovoru'), 'html: the Meet button is coral');
ok(str_contains($m['text'], "Platba 1\u{a0}100\u{a0}Kč byla přijata.") && str_contains($m['html'], "Platba <b style=\"font-weight:600\">1\u{a0}100\u{a0}Kč</b> byla přijata.") && !str_contains($m['text'] . $m['html'], 'Googlu'), 'confirmation: price paid, no Google sign-in line');
$lines = explode("\r\n", $m['ics']);
ok(max(array_map('strlen', $lines)) <= 75, 'ics: no line over 75 bytes');
ok((bool)preg_match('//u', $m['ics']), 'ics: still valid UTF-8 after folding');
ok(str_contains($m['ics'], "DTSTART;TZID=Europe/Prague:20261222T130000") && str_contains($m['ics'], 'METHOD:PUBLISH'), 'ics: start and method');
$in = mail_build(['pay_id' => 'x', 'forma' => 'osobne', 'email' => 'a@b.cz'], slot_start('2027-07-15', '9:00'), 'http://x');
ok(str_contains($in['html'], 'destination=Mgr.+Krist%C3%BDna+Hricikov%C3%A1+Glattov%C3%A1') && substr_count($in['html'], 'href="' . h(NAVIGATE) . '"') === 2, 'in person: address and Navigovat both route to her business profile');
ok(str_contains($in['text'], PLACE) && str_contains($in['text'], 'Navigace: ') && !str_contains($in['html'], 'videohovoru') && str_contains($in['html'], '#F6E6DC'), 'in person: address, no Meet, peach panel');
$eml = mail_eml($m);
ok(str_contains($eml, 'Subject: =?UTF-8?B?') && str_contains($eml, 'multipart/alternative') && str_contains($eml, 'filename="sezeni.ics"'), 'eml: encoded subject, both bodies, attachment');
file_put_contents("$tmp/test.eml", $eml);

// --- holds and cancel rules (database only)
$slot = slot_start($future, '10:00')->format(DATE_ATOM);
db()->prepare('INSERT INTO holds (slot, pay_id, expires_at) VALUES (?, ?, ?)')->execute([$slot, 'cs_test_other', time() + 600]);
throws(fn() => create_checkout(['f' => 'online', 'date' => $future, 'time' => '10:00', 'email' => 'a@b.cz']), 409, 'held slot refused before any network call');
db()->prepare('UPDATE holds SET expires_at = ? WHERE slot = ?')->execute([time() - 1, $slot]);
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_paid', ?, 'online', 'booked', 0, 0)")->execute([$slot]);
throws(fn() => create_checkout(['f' => 'osobne', 'date' => $future, 'time' => '10:00', 'phone' => '777123456']), 409, 'booked slot refused for the other format too');
$cnt = (int)db()->query('SELECT COUNT(*) FROM holds')->fetchColumn();
ok($cnt === 0, 'expired hold cleaned up');

$soon = (new DateTimeImmutable('+10 hours'))->format(DATE_ATOM);
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_late', ?, 'osobne', 'booked', 0, 0)")->execute([$soon]);
$r = cancel_booking('cs_test_late');
ok($r['status'] === 'cancelled' && $r['refund_id'] === null && abs((float)$r['hours_before'] - 10) < 0.1, 'late cancel: cancelled, no refund');
ok(cancel_booking('cs_test_late')['status'] === 'cancelled', 'second cancel returns the same, nothing new');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, status, created_at, updated_at) VALUES ('cs_test_late2', ?, 'online', 'a@b.cz', 'Jana', 'booked', 0, 0)")->execute([(new DateTimeImmutable('+11 hours'))->format(DATE_ATOM)]);
cancel_booking('cs_test_late2');
$f = cfg()['outbox'] . '/cs_test_late2-client-cancelled.eml';
ok(is_file($f) && str_contains(file_get_contents($f), 'To: <a@b.cz>'), 'client cancels: the client gets an e-mail too');
ok(!is_file(cfg()['outbox'] . '/cs_test_late-client-cancelled.eml'), 'no e-mail address: no client e-mail');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_past', ?, 'online', 'booked', 0, 0)")->execute([(new DateTimeImmutable('-5 minutes'))->format(DATE_ATOM)]);
throws(fn() => cancel_booking('cs_test_past'), 400, 'started session can not be cancelled');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_pend', ?, 'online', 'pending', 0, 0)")->execute([$soon]);
throws(fn() => cancel_booking('cs_test_pend'), 409, 'booking still processing can not be cancelled yet');
throws(fn() => cancel_booking('cs_test_none'), 404, 'unknown booking');

$v = booking_view(['slot' => $w->format(DATE_ATOM), 'forma' => 'online', 'status' => 'booked', 'refund_id' => null], $w->modify('-36 hours'));
ok($v['refund'] === true && $v['started'] === false, 'exactly 36 hours before still refunds');
$v = booking_view(['slot' => $w->format(DATE_ATOM), 'forma' => 'online', 'status' => 'booked', 'refund_id' => null], $w->modify('-36 hours +1 second'));
ok($v['refund'] === false, 'one second later no refund');

// --- free times (pure part, no Google)
$h27 = cz_holidays(2027);
ok(isset($h27['2027-03-26'], $h27['2027-03-29'], $h27['2027-12-24'], $h27['2027-11-17']) && count($h27) === 13, 'holidays 2027: Good Friday 26 Mar, Easter Monday 29 Mar, fixed days');
$h26 = cz_holidays(2026);
ok(isset($h26['2026-04-03'], $h26['2026-04-06']), 'holidays 2026: Easter 3 and 6 Apr');
$ts = fn(string $s) => prague($s)->getTimestamp();
$open = ['online' => [[$ts('2026-12-21T08:00'), $ts('2026-12-21T12:00')], [$ts('2026-12-21T13:00'), $ts('2026-12-21T15:00')]],
         'osobne' => [[$ts('2026-12-21T08:00'), $ts('2026-12-21T12:00')]]];
$from = prague('2026-12-01T00:00'); $to = prague('2027-01-31T23:59');
$r = compute_slots($open, [], $from, $to, true);
ok($r['online'] === ['2026-12-21' => ['8:00', '9:00', '10:00', '11:00', '13:00', '14:00']], 'one hour steps from the start of each block, ending inside it');
$r = compute_slots($open, [[$ts('2026-12-21T10:30'), $ts('2026-12-21T11:00')]], $from, $to, true);
ok($r['online']['2026-12-21'] === ['8:00', '9:00', '11:00', '13:00', '14:00'] && $r['osobne']['2026-12-21'] === ['8:00', '9:00', '11:00'], 'one busy event blocks the time in both formats');
$r = compute_slots($open, [[$ts('2026-12-21T09:50'), $ts('2026-12-21T10:00')]], $from, $to, true);
ok(in_array('9:00', $r['online']['2026-12-21']) && in_array('10:00', $r['online']['2026-12-21']), 'busy right after a session (9:50) blocks nothing');
$r = compute_slots(['online' => [[$ts('2026-12-21T08:30'), $ts('2026-12-21T10:00')]]], [], $from, $to, true);
ok($r['online'] === ['2026-12-21' => ['8:30']], 'block 8:30-10:00 gives 8:30 only (9:30 would end after 10:00)');
$r = compute_slots($open, [], prague('2026-12-21T09:30'), $to, true);
ok($r['online']['2026-12-21'][0] === '10:00', 'nothing before the minimum notice');
$r = compute_slots($open, [], $from, prague('2026-12-21T10:00'), true);
ok(end($r['online']['2026-12-21']) === '10:00', 'nothing after the last bookable moment');
$r = compute_slots(['online' => [[$ts('2026-12-24T08:00'), $ts('2026-12-24T12:00')]]], [], $from, $to, true);
ok($r['online'] === [], 'public holiday skipped');
ok(compute_slots(['online' => [[$ts('2026-12-24T08:00'), $ts('2026-12-24T09:00')]]], [], $from, $to, false)['online'] !== [], 'public holidays can be kept (setting)');
$r = compute_slots(['online' => [[$ts('2026-12-22T00:00'), $ts('2026-12-23T00:00')]]], [], $from, $to, true);
ok($r['online']['2026-12-22'][0] === '6:00' && end($r['online']['2026-12-22']) === '20:00', 'an all-day hours event offers only 6:00-20:00');
$r = compute_slots(['online' => [[$ts('2027-03-28T08:00'), $ts('2027-03-28T11:00')]]], [], $from, prague('2027-04-30T00:00'), true);
ok($r['online'] === ['2027-03-28' => ['8:00', '9:00', '10:00']], 'summer time day: local times stay whole hours');
$r = compute_slots(['online' => [[$ts('2026-12-21T08:00'), $ts('2026-12-21T12:00')], [$ts('2026-12-21T08:00'), $ts('2026-12-21T10:00')]]], [], $from, $to, true);
ok($r['online']['2026-12-21'] === ['8:00', '9:00', '10:00', '11:00'], 'overlapping hours blocks give each time once');
[$wf, $wt] = slot_window(prague('2026-12-21T10:15'));
ok($wf->format('Y-m-d H:i') === '2026-12-22 10:15' && $wt->format('Y-m-d H:i') === '2027-03-01 23:59', 'window: 24 hours notice, 70 days ahead to the end of the day');

// --- the owner's links and e-mails
$ot = owner_token('cs_test_abc');
ok(owner_token_ok('cs_test_abc', $ot), 'owner token accepted');
ok(!owner_token_ok('cs_test_abc', cancel_token('cs_test_abc')), "client's cancel token is not an owner token");
ok(!token_ok('cs_test_abc', $ot), "owner token is not a client's cancel token");
ok(!owner_token_ok('owner:cs_test_abc', cancel_token('owner:cs_test_abc')), 'no forging through the owner: prefix');
ok(str_starts_with(owner_url('cs_test_abc'), 'http://127.0.0.1:8820/rezervace/sprava.html?b=cs_test_abc&t='), 'owner URL');
$ob = ['pay_id' => 'cs_test_abc', 'forma' => 'online', 'name' => 'Jana <b>Nováková</b>', 'email' => 'jana@example.com', 'phone' => null,
    'note' => "Úzkosti\npřed zkouškami & <script>", 'clash' => 1];
$om = mail_owner_new($ob, $w, owner_url('cs_test_abc'));
ok($om['to'] === OWNER_MAIL && $om['reply_to'] === 'jana@example.com' && $om['ics'] === null, 'owner e-mail: to her, replies go to the client, no .ics');
ok($om['subject'] === 'Nová rezervace: Jana <b>Nováková</b> · út 22. 12. v 13:00 · online', 'owner e-mail subject');
ok(str_contains($om['html'], 'Jana &lt;b&gt;Nováková&lt;/b&gt;') && str_contains($om['html'], '&amp; &lt;script&gt;') && !str_contains($om['html'], '<script>'), 'owner e-mail: client text escaped');
ok(str_contains($om['html'], 'Úzkosti<br />') && str_contains($om['html'], 'jiná událost') && str_contains($om['html'], '&amp;akce=presunout') && str_contains($om['html'], '&amp;akce=zrusit'), 'owner e-mail: note, clash warning, both buttons');
$eml = mail_eml($om);
ok(!str_contains($eml, 'sezeni.ics') && str_contains($eml, 'Reply-To: <jana@example.com>') && str_contains($eml, 'To: <' . OWNER_MAIL . '>'), 'owner eml: no attachment, reply-to client');
$mv = mail_build($ob + ['moves' => 2], $w, 'http://x', slot_start('2026-12-21', '9:00'));
ok($mv['subject'] === 'Změna termínu: sezení v úterý 22. prosince v 13:00' && str_contains($mv['text'], 'Původní termín v pondělí 21. prosince v 9:00 už neplatí.'), 'moved: subject and old time');
ok(str_contains($mv['ics'], 'SEQUENCE:2') && str_contains($mv['ics'], 'UID:cs_test_abc@'), 'moved: same calendar UID, higher SEQUENCE');
$cc = mail_client_cancelled($ob, $w, false);
ok($cc['subject'] === 'Zrušené sezení v úterý 22. prosince v 13:00' && str_contains($cc['text'], 'Částka bude vrácena na kartu použitou k platbě') && str_contains($cc['text'], '/rezervace.html'), 'owner cancels: client e-mail with a link to book again');
$cr = mail_client_cancelled($ob, $w, true);
ok(str_starts_with($cr['subject'], 'Vrácení platby za sezení') && str_starts_with($cr['text'], 'Vrácení platby') && str_contains($cr['text'], 'Částka bude vrácena na kartu použitou k platbě') && !str_contains($cr['text'], 'Vybrat jiný termín'), 'refund after a late cancellation: own wording');
$cl = mail_client_cancelled($ob, $w, false, false);
ok(str_contains($cl['text'], 'Platba se nevrací, termín byl zrušen méně než 36 hodin předem.') && !str_contains($cl['text'], 'Částka bude vrácena') && str_contains($cl['text'], 'Vybrat jiný termín'), 'client cancelled late: e-mail says no refund, offers a new time');
$oc = mail_owner_cancelled($ob + ['refund_id' => null, 'hours_before' => 10.2], $w, owner_url('cs_test_abc'));
ok(str_contains($oc['text'], 'Zrušeno jen 10 h předem') && !str_contains($oc['html'], 'Vrátit') && !str_contains($oc['html'], 'sprava.html'), 'client cancelled late: no refund button for the owner');
$oc = mail_owner_cancelled($ob + ['refund_id' => 're_1', 'hours_before' => 50], $w, owner_url('cs_test_abc'));
ok(str_contains($oc['text'], 'vrací automaticky') && !str_contains($oc['html'], 'Vrátit peníze'), 'client cancelled early: no refund button');
db()->prepare('INSERT INTO notes (pay_id, note, created_at) VALUES (?, ?, ?)')->execute(['cs_test_old', 'x', time() - 3 * 86400]);
db()->prepare('DELETE FROM notes WHERE created_at < ?')->execute([time() - 2 * 86400]);
ok(!db()->query("SELECT COUNT(*) FROM notes WHERE pay_id = 'cs_test_old'")->fetchColumn(), 'notes of unpaid checkouts are forgotten after 2 days');
ok(cz_ahead(10.2) === '10 h' && cz_ahead(47.6) === '48 h' && cz_ahead(50) === '2 dny' && cz_ahead(771) === '32 dní', 'cancel lead time in hours or days');
throws(fn() => owner_move('cs_test_late', $future, '10:00'), 409, 'a cancelled booking can not be moved');
$long = 'a' . str_repeat('ž', 900);
ok(cut($long, 1500) === $long && strlen(cut('a' . str_repeat('ž', 1600), 1500)) === 1 + 1499 * 2, 'a long Czech note is cut by letters, not dropped');
ok(cut("Jana \xC5", 100) === '' && cut('  Jiří Novák  ', 100) === 'Jiří Novák', 'broken UTF-8 is refused, names are trimmed');

// --- the client's move (rules checked before any Google call)
$ins = db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, status, client_moves, created_at, updated_at) VALUES (?, ?, 'online', 'a@example.com', 'Jana', 'booked', ?, 0, 0)");
$in20h = (new DateTimeImmutable('+20 hours'))->format(DATE_ATOM);
$in5d = (new DateTimeImmutable('+5 days'))->setTime(10, 0)->format(DATE_ATOM);
$ins->execute(['cs_test_soon', $in20h, 0]);
$ins->execute(['cs_test_moved', $in5d, 1]);
$ins->execute(['cs_test_free', $in5d, 0]);
$later = (new DateTimeImmutable('+6 days'))->format('Y-m-d');
throws(fn() => client_move('cs_test_soon', $later, '10:00'), 409, 'client move under 36 hours before is refused');
throws(fn() => client_move('cs_test_late', $later, '10:00'), 409, 'client can not move a cancelled booking');
ok(booking_view(booking_get('cs_test_free'))['can_move'] && booking_view(booking_get('cs_test_moved'))['can_move']
    && !booking_view(booking_get('cs_test_soon'))['can_move'], 'a move is offered while 36+ hours before, also after earlier moves');
$fb = booking_get('cs_test_free');
$mc = mail_build($fb, $w, cancel_url('cs_test_free'));
ok(str_contains($mc['text'], 'Přesunout nebo zdarma zrušit lze nejpozději 36 hodin') && str_contains($mc['text'], 'Přesunout nebo zrušit termín: '), 'confirmation offers move and cancel');
$mm = mail_build(['client_moves' => 1] + $fb, $w, cancel_url('cs_test_free'), $w->modify('-1 day'), true);
ok(str_starts_with($mm['text'], 'Změna termínu') && str_contains($mm['text'], 'Původní termín v pondělí 21. prosince v 13:00 už neplatí.') && str_contains($mm['text'], 'Přesunout nebo zrušit termín: '), 'after the client moved: own wording, still movable');
$om = mail_owner_moved($fb, $w, $w->modify('-1 day'), owner_url('cs_test_free'));
ok(str_starts_with($om['subject'], 'Přesunuto klientem: Jana') && str_contains($om['text'], 'Původně v pondělí 21. prosince') && !str_contains($om['text'], 'přesun od klienta'), 'owner told about the client move');
ok(str_contains(mail_owner_moved(['client_moves' => 3] + $fb, $w, $w->modify('-1 day'), owner_url('cs_test_free'))['text'], 'Už je to 3. přesun od klienta.'), 'repeated moves show the count to the owner');
$od = mail_owner_deleted($fb, $w, owner_url('cs_test_free'));
ok(str_starts_with($od['text'], 'Smazáno v kalendáři') && str_contains($od['text'], 'akce=zrusit'), 'owner told about her calendar deletion, with the refund button');
$dl = mail_owner_declined($fb, $w, owner_url('cs_test_free'));
ok(str_contains($dl['text'], 'Rezervace platí dál') && $dl['reply_to'] === 'a@example.com', 'owner told about a declined invitation');

// --- hold limit per visitor (holds without a Stripe session yet: no Stripe call needed)
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$me = visitor();
ok(strlen($me) === 16 && !str_contains($me, '203') && $me !== (function () { $_SERVER['REMOTE_ADDR'] = '203.0.113.8'; $o = visitor(); $_SERVER['REMOTE_ADDR'] = '203.0.113.7'; return $o; })(), 'visitor key is a hash, different per IP');
$hold = db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who) VALUES (?, NULL, ?, ?)');
foreach ([1, 2, 3] as $i) $hold->execute(["2027-01-0{$i}T10:00:00+01:00", time() + 600 + $i, $me]);
$hold->execute(['2027-01-05T10:00:00+01:00', time() + 600, 'someoneelse00000']);
cap_holds($me, time());
$left = db()->query("SELECT slot FROM holds WHERE who = '$me' ORDER BY slot")->fetchAll(PDO::FETCH_COLUMN);
ok($left === ['2027-01-02T10:00:00+01:00', '2027-01-03T10:00:00+01:00'], 'a 4th hold frees the visitor\'s oldest, keeping 2 + the new one');
ok((int)db()->query("SELECT COUNT(*) FROM holds WHERE who = 'someoneelse00000'")->fetchColumn() === 1, 'other visitors\' holds untouched');
db()->exec("DELETE FROM holds WHERE slot LIKE '2027-01-0%'");

// --- reminders: 24 hours and 10 minutes before
$now = time();
$rs = db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, meet, status, reminded, created_at, updated_at) VALUES (?, ?, ?, 'r@example.com', 'R', 'https://meet.google.com/abc-defg-hij', 'booked', 0, ?, 0)");
$rs->execute(['cs_test_rday', date(DATE_ATOM, $now + 23 * 3600 + 50 * 60), 'online', $now - 5 * 86400]);   // booked days ago
$rs->execute(['cs_test_rnew', date(DATE_ATOM, $now + 23 * 3600 + 30 * 60), 'online', $now - 3600]);        // booked an hour ago
$rs->execute(['cs_test_rsoon', date(DATE_ATOM, $now + 8 * 60), 'osobne', $now - 5 * 86400]);
$rs->execute(['cs_test_rfar', date(DATE_ATOM, $now + 60 * 3600), 'osobne', $now - 5 * 86400]);
$mine = fn(array $r) => array_values(array_filter($r, fn($x) => str_starts_with($x, 'cs_test_r')));   // other tests' bookings may get theirs too
$got = $mine(send_reminders($now));
sort($got);
ok($got === ['cs_test_rday:day', 'cs_test_rsoon:soon'], 'day-before reminder 24 h ahead, short one 10 min ahead, none for a fresh booking or later');
ok((int)booking_get('cs_test_rnew')['reminded'] === 1, 'a fresh booking skips the day-before reminder (confirmation just came)');
ok(send_reminders($now) === [], 'each reminder once');
ok($mine(send_reminders($now + 23 * 3600 + 22 * 60)) === ['cs_test_rnew:soon'] && $mine(send_reminders($now + 23 * 3600 + 42 * 60)) === ['cs_test_rday:soon'], 'the short one when its time comes');
$rd = mail_reminder(booking_get('cs_test_rday'), prague(booking_get('cs_test_rday')['slot']), 'day', $now);
ok(str_starts_with($rd['subject'], 'Připomínka: sezení zítra v ') && str_contains($rd['text'], 'meet.google.com/abc-defg-hij')
    && !str_contains($rd['text'] . $rd['html'], 'Zrušit termín') && !preg_match('/Kdybyste|Těším se/u', $rd['text'] . $rd['html']), 'day reminder: tomorrow, Meet link, no cancel link under 36 hours, no filler');
$ro = mail_reminder(booking_get('cs_test_rsoon'), prague(booking_get('cs_test_rsoon')['slot']), 'soon', $now);
ok(str_starts_with($ro['subject'], 'Za 10 minut: sezení v ') && str_contains($ro['text'], 'Vítkovická') && !str_contains($ro['text'], 'zruseni'), 'short reminder in person: address, no cancel link');
db()->prepare('UPDATE bookings SET reminded = 0, moved_at = ? WHERE pay_id = ?')->execute([$now + 36 * 3600, 'cs_test_rfar']);   // moved an hour before
ok($mine(send_reminders($now + 37 * 3600)) === [] && (int)booking_get('cs_test_rfar')['reminded'] === 1, 'just moved: no day reminder right after the change e-mail');

// --- the owner's hours page: hours in the database
$nowH = prague('2026-11-14T10:00:00+01:00');
ok(cz_holiday_names(2027)['2027-03-26'] === 'Velký pátek' && count(cz_holiday_names(2026)) === 13, 'holiday names incl. Easter, 13 a year');
$v0 = hours_version();
hours_save(['online' => [1 => [9, 10], 2 => [17, 8]], 'osobne' => [4 => [13]]], [], $v0, 'test', $nowH);
ok(hours_week()['online'][2] === [8, 17], 'hours are sorted and kept');
throws(fn() => hours_save(['online' => [1 => [18]]], [], hours_version(), 'test', $nowH), 400, 'an hour after 17:00 refused');
throws(fn() => hours_save(['online' => [1 => ['9']]], [], hours_version(), 'test', $nowH), 400, 'an hour as text refused');
throws(fn() => hours_save(['hack' => [1 => [9]]], [], hours_version(), 'test', $nowH), 400, 'unknown format refused');
throws(fn() => hours_save([], ['online' => ['2026-11-13' => [9]]], hours_version(), 'test', $nowH), 400, 'a day gone by refused');
throws(fn() => hours_save([], ['online' => ['2026-02-30' => [9]]], hours_version(), 'test', $nowH), 400, 'an impossible date refused');
throws(fn() => hours_save([], [], $v0, 'test', $nowH), 409, 'a save from an old version refused (another device saved meanwhile)');
// Mon 16 Nov: week 9, 10. Tue 17 Nov: holiday (closed though Tuesday has 8, 17). Sat 21 Nov: own hours 14. Mon 23: closed for the day.
hours_save([], ['online' => ['2026-11-21' => [14], '2026-11-23' => []]], hours_version(), 'test', $nowH);
$o = hours_open(prague('2026-11-16T00:00:00+01:00'), prague('2026-11-23T23:59:00+01:00'))['online'];
$hrs = array_map(fn($b) => date('Y-m-d G', $b[0]), $o);
ok(in_array('2026-11-16 9', $hrs, true) && in_array('2026-11-16 10', $hrs, true) && !in_array('2026-11-16 11', $hrs, true), 'a regular Monday has its weekly hours');
ok(!array_filter($hrs, fn($x) => str_starts_with($x, '2026-11-17')), 'a holiday is closed by default');
ok(in_array('2026-11-21 14', $hrs, true) && !array_filter($hrs, fn($x) => str_starts_with($x, '2026-11-23')), 'a day of its own: Saturday open at 14, Monday closed');
ok($o[0][1] - $o[0][0] === 3600, 'each hour is an hour-long block');
hours_save([], ['online' => ['2026-11-17' => [10]]], hours_version(), 'test', $nowH);
$r = compute_slots(hours_open(prague('2026-11-17T00:00:00+01:00'), prague('2026-11-17T23:59:00+01:00')), [], prague('2026-11-17T00:00:00+01:00'), prague('2026-11-17T23:59:00+01:00'), false);
ok(($r['online']['2026-11-17'] ?? null) === ['10:00'], 'a holiday she opened herself is bookable');
hours_save([], ['online' => ['2026-11-21' => null]], hours_version(), 'test', $nowH);
ok(!isset(hours_days('2026-11-21', '2026-11-21')['online']['2026-11-21']), 'null puts a day back on the regular week');
$sp = hours_open(prague('2027-04-05T00:00:00+02:00'), prague('2027-04-05T23:00:00+02:00'))['online'];   // a summer-time Monday (29 Mar is Easter Monday)
ok(date('G', $sp[0][0]) === '9', 'summer time: 9:00 stays 9:00');
$hv = hours_view($nowH);
ok($hv['first_hour'] === 8 && $hv['last_hour'] === 17 && isset(((array)$hv['holidays'])['2026-11-17']), 'the page gets 8–17 and the holidays');

// --- the owner's hours page: sign-in
$d1 = owner_login('spravne-heslo-42', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0 Safari/537.36', 'w1');
ok($d1['label'] === 'Chrome · Windows' && strlen($d1['token']) >= 40, 'right password: a device "Chrome · Windows" with a long random token');
ok(device_label('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0') === 'Firefox · Ubuntu'
    && device_label('Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36') === 'Chrome · Android', 'device names for Ubuntu and a phone');
ok(owner_device($d1['token'])['id'] === $d1['id'], 'the cookie signs the device in');
ok((string)db()->query("SELECT token_hash FROM owner_devices WHERE id = '{$d1['id']}'")->fetchColumn() !== $d1['token'], 'only a hash of the token is stored');
ok(owner_device('nesmysl') === null && owner_device(null) === null, 'a made-up cookie or none: not signed in');
ok(owner_device($d1['token'], time() + 366 * 86400) === null, 'a device unused for over a year is signed out');
for ($i = 0; $i < 5; $i++) { try { owner_login('spatne', 'x', 'w2'); } catch (ClientError $e) {} }
throws(fn() => owner_login('spravne-heslo-42', 'x', 'w2'), 429, 'after 5 wrong passwords even the right one waits 15 minutes');
ok(owner_login('spravne-heslo-42', 'x', 'w3')['id'] !== '', 'another visitor is not locked by them');
db()->exec('DELETE FROM login_fails');
for ($i = 0; $i < 30; $i++) { try { owner_login('spatne', 'x', 'wx' . $i); } catch (ClientError $e) {} }
throws(fn() => owner_login('spravne-heslo-42', 'x', 'w4'), 429, '30 wrong passwords from many addresses lock everyone for a while');
db()->exec('DELETE FROM login_fails');
$d2 = owner_login('spravne-heslo-42', 'x', 'w5');
ok(count(array_filter(owner_devices($d1['id']), fn($x) => $x['current'])) === 1, 'the device list marks this device');
owner_logout($d2['id'], $d1['id']);
ok(owner_device($d2['token']) === null && owner_device($d1['token']) !== null, 'signing out one device leaves the others');

echo "\n$n checks, $fails failed. Test e-mail: $tmp/test.eml\n";
exit($fails ? 1 : 0);
