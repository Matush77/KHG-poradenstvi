<?php
/* Offline tests (no Stripe, no Google): php booking/tests/run.php
   Runs against a throwaway config dir with a dummy test key, so nothing real is touched. */
declare(strict_types=1);

// never from the web (16 Nov review: an address trick could reach this file, and it does heavy password work)
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

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

// --- config folder per site copy
$keep = getenv('KHG_CONFIG_DIR'); putenv('KHG_CONFIG_DIR');
ok(config_dir('/home/x/public_html') === '/home/x/.config/khg', 'live site: ~/.config/khg');
ok(config_dir('/home/x/test_html') === '/home/x/.config/khg-test', 'test subdomain: its own ~/.config/khg-test');
putenv("KHG_CONFIG_DIR=$keep");
[, $m] = parse_choice(['f' => 'online', 'date' => date('Y-m-d', strtotime('+10 days')), 'time' => '10:00', 'name' => 'Jana Nováková', 'email' => 'a@b.cz']);
ok($m['kopie'] === site_copy() && site_copy() === basename(getenv('KHG_CONFIG_DIR')), 'each payment names its site copy (sweep books only its own)');

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
throws(fn() => parse_choice(['f' => 'online', 'date' => '2020-01-01', 'name' => 'Jana Nováková', 'time' => '9:00', 'email' => 'a@b.cz']), 400, 'past slot refused');
throws(fn() => parse_choice(['f' => 'online', 'date' => (new DateTimeImmutable('+200 days'))->format('Y-m-d'), 'name' => 'Jana Nováková', 'time' => '9:00', 'email' => 'a@b.cz']), 400, 'slot too far ahead refused');
$future = (new DateTimeImmutable('+10 days'))->format('Y-m-d');
throws(fn() => parse_choice(['f' => 'online', 'date' => $future, 'name' => 'Jana Nováková', 'time' => '9:00', 'email' => '']), 400, 'online without e-mail refused');
throws(fn() => parse_choice(['f' => 'online', 'date' => $future, 'name' => 'Jana Nováková', 'time' => '9:00', 'email' => 'nope']), 400, 'bad e-mail refused');
[$s, $meta] = parse_choice(['f' => 'osobne', 'date' => $future, 'time' => '9:00', 'phone' => '+420 777 123 456', 'name' => 'Jana  Nováková ', 'price' => 1]);
ok($meta['forma'] === 'osobne' && $meta['cas'] === '9:00' && !isset($meta['price']), 'in person without e-mail accepted, price from the browser ignored');
throws(fn() => parse_choice(['f' => 'osobne', 'date' => $future, 'time' => '9:00', 'name' => 'Jana Nováková', 'email' => 'a@b.cz']), 400, 'in person without a phone refused');
throws(fn() => parse_choice(['f' => 'osobne', 'date' => $future, 'time' => '9:00', 'name' => 'Jana Nováková', 'phone' => '777 12']), 400, 'in person with a too short phone refused');
ok($meta['jmeno'] === 'Jana Nováková', 'name spaces tidied');
foreach (['', 'Jana', 'Jana N.', '  Jana  '] as $nm)
    throws(fn() => parse_choice(['f' => 'online', 'date' => $future, 'time' => '9:00', 'name' => $nm, 'email' => 'a@b.cz']), 400, "name without a surname refused: '$nm'");
[, $meta] = parse_choice(['f' => 'online', 'date' => $future, 'time' => '9:00', 'name' => 'Jan Novák-Dvořák', 'email' => 'a@b.cz']);
ok($meta['jmeno'] === 'Jan Novák-Dvořák', 'double surname accepted');
[, $meta] = parse_choice(['f' => 'hack', 'date' => $future, 'time' => '9:00', 'name' => 'Jana Nováková', 'email' => 'a@b.cz']);
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
throws(fn() => create_checkout(['f' => 'online', 'date' => $future, 'name' => 'Jana Nováková', 'time' => '10:00', 'email' => 'a@b.cz']), 409, 'held slot refused before any network call');
db()->prepare('UPDATE holds SET expires_at = ? WHERE slot = ?')->execute([time() - 1, $slot]);
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_paid', ?, 'online', 'booked', 0, 0)")->execute([$slot]);
throws(fn() => create_checkout(['f' => 'osobne', 'date' => $future, 'name' => 'Jana Nováková', 'time' => '10:00', 'phone' => '777123456']), 409, 'booked slot refused for the other format too');
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
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_test_promised', ?, 'online', 'booked', 0, 0)")->execute([date(DATE_ATOM, time() + 20 * 3600)]);
throws(fn() => cancel_booking('cs_test_promised', null, true), 409, 'the page promised a refund but 36 h ran out meanwhile: nothing is cancelled');
ok(booking_get('cs_test_promised')['status'] === 'booked', '...and the booking stays');
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
ok($om['subject'] === 'Schůzka byla rezervována: Terapie online (Jana <b>Nováková</b>) v úterý 22. prosince v 13:00', 'owner e-mail subject, worded like Google\'s');
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
// retention (owner, 15 Nov): the message 30 days after the session, the booking 3 years after it
$fo = db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, note, status, created_at, updated_at) VALUES (?, ?, 'online', 'a@example.com', 'Jana', 'vzkaz', ?, 0, 0)");
$fo->execute(['cs_ret_29d', (new DateTimeImmutable('-29 days'))->format(DATE_ATOM), 'booked']);
$fo->execute(['cs_ret_31d', (new DateTimeImmutable('-31 days'))->format(DATE_ATOM), 'booked']);
$fo->execute(['cs_ret_old', (new DateTimeImmutable('-3 years -1 day'))->format(DATE_ATOM), 'cancelled']);
$fo->execute(['cs_ret_2y', (new DateTimeImmutable('-2 years'))->format(DATE_ATOM), 'booked']);
$fo->execute(['cs_ret_next', (new DateTimeImmutable('+3 days'))->format(DATE_ATOM), 'booked']);
$gone = forget_old();
$note = fn($id) => db()->query("SELECT note FROM bookings WHERE pay_id = '$id'")->fetchColumn();
ok($note('cs_ret_29d') === 'vzkaz' && $note('cs_ret_next') === 'vzkaz', 'the message stays until 30 days after the session');
ok($note('cs_ret_31d') === null && $note('cs_ret_2y') === null, 'the message is forgotten 30 days after the session, the booking stays');
ok(db()->query("SELECT COUNT(*) FROM bookings WHERE pay_id = 'cs_ret_old'")->fetchColumn() == 0
    && db()->query("SELECT COUNT(*) FROM bookings WHERE pay_id = 'cs_ret_2y'")->fetchColumn() == 1, 'a booking is deleted 3 years after the session');
ok(($gone['bookings_forgotten'] ?? 0) >= 1 && ($gone['notes_forgotten'] ?? 0) >= 2, 'forget_old reports what it removed');
db()->exec("DELETE FROM bookings WHERE pay_id LIKE 'cs_ret_%'");
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

// --- her move from the hours page (16 Nov): any whole hour 7-20 in the window, outside her hours too, never onto another session
$d6 = (new DateTimeImmutable('+6 days', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
$ins->execute(['cs_test_other', (new DateTimeImmutable($d6 . ' 18:30', new DateTimeZone('Europe/Prague')))->format(DATE_ATOM), 0]);
throws(fn() => owner_move('cs_test_free', $d6, '6:00'), 400, 'owner move before 7:00 refused');
throws(fn() => owner_move('cs_test_free', $d6, '21:00'), 400, 'owner move after 20:00 refused');
throws(fn() => owner_move('cs_test_free', $d6, '9:30'), 400, 'owner move off the hour refused');
throws(fn() => owner_move('cs_test_free', (new DateTimeImmutable('-1 day'))->format('Y-m-d'), '10:00'), 400, 'owner move into the past refused');
throws(fn() => owner_move('cs_test_free', (new DateTimeImmutable('+200 days'))->format('Y-m-d'), '10:00'), 400, 'owner move beyond the booking window refused');
throws(fn() => owner_move('cs_test_free', $d6, '18:00'), 409, 'owner move next to a session at 18:30 refused (18:00)');
throws(fn() => owner_move('cs_test_free', $d6, '19:00'), 409, 'owner move next to a session at 18:30 refused (19:00)');
db()->prepare('INSERT INTO holds (slot, expires_at, who, browser) VALUES (?, ?, ?, ?)')
    ->execute([(new DateTimeImmutable($d6 . ' 8:00', new DateTimeZone('Europe/Prague')))->format(DATE_ATOM), time() + 600, 'v-x', 'b-x']);
throws(fn() => owner_move('cs_test_free', $d6, '8:00'), 409, 'owner move onto a time someone is paying for refused');
$mv = owner_move('cs_test_free', $d6, '20:00');
ok(prague($mv['slot'])->format('Y-m-d G:i') === $d6 . ' 20:00' && (int)$mv['moves'] === 1 && (int)$mv['client_moves'] === 0, 'owner moves outside her hours (20:00, no hours set at all)');
db()->exec("DELETE FROM bookings WHERE pay_id = 'cs_test_other'"); db()->exec("DELETE FROM holds WHERE browser = 'b-x'");
booking_update('cs_test_free', ['slot' => $in5d, 'moves' => 0]);

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

// --- back from Stripe's page: the browser's own hold does not block it, anyone else's does
$_COOKIE[HOLD_COOKIE] = str_repeat('a', 24);
$mine = browser_id();
ok(strlen($mine) === 16 && $mine !== str_repeat('a', 16), 'browser key is a hash of the cookie');
$_COOKIE[HOLD_COOKIE] = 'bad cookie!';
ok(browser_id() === null && strlen((string)browser_id(true)) === 16 && preg_match('/^[A-Za-z0-9_-]{24}$/', $_COOKIE[HOLD_COOKIE]), 'no or bad cookie: none, or a new random one at checkout');
$_COOKIE[HOLD_COOKIE] = str_repeat('a', 24);
$own = '2027-01-12T10:00:00+01:00';
db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser) VALUES (?, NULL, ?, ?, ?)')->execute([$own, time() + 600, $me, $mine]);
ok(!in_array($own, taken_slots($mine), true) && in_array($own, taken_slots(null), true) && in_array($own, taken_slots('otherbrowser0000'), true), 'own hold is free for this browser only');
try { create_checkout(['f' => 'online', 'date' => '2027-01-12', 'name' => 'Jana Nováková', 'time' => '10:00', 'email' => 'a@b.cz']); $code = 0; }
catch (ClientError $e) { $code = $e->getCode(); }
ok($code !== 409, "same browser may pay for its held time again (got $code offline, not 409)");
db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser) VALUES (?, NULL, ?, ?, ?)')->execute([$own, time() + 600, $me, 'otherbrowser0000']);
throws(fn() => create_checkout(['f' => 'online', 'date' => '2027-01-12', 'name' => 'Jana Nováková', 'time' => '10:00', 'email' => 'a@b.cz']), 409, 'another browser\'s hold still refused, even from the same IP');
db()->exec("DELETE FROM holds WHERE slot = '$own'");

// --- back from Stripe unpaid: only this browser's finished Stripe pages are released (the Stripe call itself is checked live)
ok(release_browser_holds(null) === [], 'no cookie: nothing released');
$hb = db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser) VALUES (?, ?, ?, ?, ?)');
$hb->execute(['2027-01-13T10:00:00+01:00', null, time() + 600, $me, $mine]);                       // still being created
$hb->execute(['2027-01-13T11:00:00+01:00', 'cs_test_otherbrowser', time() + 600, $me, 'otherbrowser0000']);
$hb->execute(['2027-01-13T13:00:00+01:00', 'cs_test_gone', time() - 5, $me, $mine]);              // already run out
ok(release_browser_holds($mine) === [] && (int)db()->query("SELECT COUNT(*) FROM holds WHERE slot LIKE '2027-01-13%'")->fetchColumn() === 3, 'a hold still being created, another browser\'s and an expired one are left alone');
db()->exec("DELETE FROM holds WHERE slot LIKE '2027-01-13%'");
$hb->execute(['2027-01-14T10:00:00+01:00', 'cs_test_tab_a', time() + 600, $me, $mine]);
$hb->execute(['2027-01-14T11:00:00+01:00', 'cs_test_tab_b', time() + 600, $me, $mine]);
ok(array_column(holds_to_release($mine, 'cs_test_tab_a'), 'pay_id') === ['cs_test_tab_a'], 'back in one tab: only that tab\'s Stripe page is ended, the other tab keeps paying');
ok(holds_to_release('otherbrowser0000', 'cs_test_tab_a') === [], 'a Stripe id from another browser releases nothing');
ok(count(holds_to_release($mine)) === 2, 'without an id (page from before): all of this browser\'s open Stripe pages, as before');
db()->exec("DELETE FROM holds WHERE slot LIKE '2027-01-14%'");

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
$rdWhen = prague(booking_get('cs_test_rday')['slot'])->format('Y-m-d') === prague(date(DATE_ATOM, $now))->format('Y-m-d') ? 'dnes' : 'zítra';   // just after midnight it is still today
ok(str_starts_with($rd['subject'], "Připomínka: sezení $rdWhen v ") && str_contains($rd['text'], 'meet.google.com/abc-defg-hij')
    && !str_contains($rd['text'] . $rd['html'], 'Zrušit termín') && !preg_match('/Kdybyste|Těším se/u', $rd['text'] . $rd['html']), 'day reminder: tomorrow (or today just after midnight), Meet link, no cancel link under 36 hours, no filler');
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
$ts = fn($s) => prague($s)->getTimestamp();
$bh = busy_hours([[$ts('2026-12-22T08:00:00+01:00'), $ts('2026-12-22T08:50:00+01:00')], [$ts('2026-12-22T11:30:00+01:00'), $ts('2026-12-22T12:10:00+01:00')],
    [$ts('2026-12-23T00:00:00+01:00'), $ts('2026-12-24T00:00:00+01:00')]], prague('2026-12-22T00:00:00+01:00'), prague('2026-12-23T23:59:00+01:00'));
ok(array_keys($bh['2026-12-22']) === [8, 11, 12], 'busy hours: a client 8:00–8:50 blocks 8 only; 11:30–12:10 blocks 11 and 12 (same rule as the booking page)');
ok(count($bh['2026-12-23']) === OWNER_MOVE_LAST - OWNER_MOVE_FIRST + 1, 'a busy all-day event blocks every hour of the day');

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
ok(owner_login('spravne-heslo-42', 'x', 'w4')['id'] !== '', '30 wrong passwords from strangers do not lock her out of a new sign-in');
for ($i = 30; $i < LOGIN_FAILS_ALL; $i++) { try { owner_login('spatne', 'x', 'wx' . $i); } catch (ClientError $e) {} }
throws(fn() => owner_login('spravne-heslo-42', 'x', 'w4b'), 429, LOGIN_FAILS_ALL . ' wrong passwords from many addresses lock everyone for a while');
db()->exec('DELETE FROM login_fails');
$d2 = owner_login('spravne-heslo-42', 'x', 'w5');
ok(count(array_filter(owner_devices($d1['id']), fn($x) => $x['current'])) === 1, 'the device list marks this device');
owner_logout($d2['id'], $d1['id']);
ok(owner_device($d2['token']) === null && owner_device($d1['token']) !== null, 'signing out one device leaves the others');

// --- 16 Nov bug hunt
ok(smtp_over_limit("SMTP: expected 250, got 550-You have reached your daily limit for sending email. Please try again\r\n550 later."), 'the mailbox limit error is recognised');
ok(!smtp_over_limit('SMTP: expected 235, got 535 Authentication failed'), 'a wrong password is not a limit');
ok(mail_paused() === 0, 'mail not paused at first');
state_set('mail_paused_until', (string)(time() + 600));
ok(mail_paused() > time(), 'mail paused after a limit error');
state_set('mail_paused_until', (string)(time() - 1));
ok(mail_paused() === 0, 'the pause ends by itself');
ok(book_paid(['id' => 'cs_test_othercopy', 'payment_status' => 'paid', 'metadata' => ['den' => $future, 'cas' => '10:00', 'forma' => 'online', 'kopie' => 'khg-elsewhere', 'email' => 'a@b.cz', 'jmeno' => 'Jana Nováková', 'telefon' => '']]) === null
    && booking_get('cs_test_othercopy') === null, "another copy's payment (shared Stripe test account) is never booked here");
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, status, created_at, updated_at) VALUES ('cs_test_bh2_past', ?, 'online', 'a@b.cz', 'booked', 0, 0)")
    ->execute([(new DateTimeImmutable('-2 hours'))->format(DATE_ATOM)]);
throws(fn() => owner_cancel('cs_test_bh2_past'), 409, 'no refund once the session has taken place');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, status, cancelled_by, hours_before, created_at, updated_at) VALUES ('cs_test_bh_delafter', ?, 'online', 'a@b.cz', 'cancelled', 'calendar', -1.5, 0, 0)")
    ->execute([(new DateTimeImmutable('-3 hours'))->format(DATE_ATOM)]);
throws(fn() => owner_cancel('cs_test_bh_delafter'), 409, 'no refund for a session deleted from the calendar after it started');

// --- 16 Nov review: the eight problems, each with Stripe and Google answers made up on purpose
$FAKE = [];
$CALLS = [];
$GLOBALS['HTTP_FAKE'] = function (string $m, string $u, ?string $b, array $h) use (&$FAKE, &$CALLS) {
    $CALLS[] = "$m $u";
    if (str_contains($u, 'oauth2.googleapis.com')) return [200, ['access_token' => 'fake', 'expires_in' => 3600]];
    foreach ($FAKE as $re => $fn) if (preg_match($re, "$m $u")) return $fn($m, $u, $b, $h);
    return [599, ['error' => ['message' => "no fake for $m $u"]]];
};
$at = fn(string $rel) => (new DateTimeImmutable($rel))->setTime(10, 0)->format(DATE_ATOM);
$add = db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, status, event_id, refund_id, refund_status, created_at, updated_at) VALUES (?, ?, ?, 'a@example.com', 'Jana Nováková', ?, ?, ?, ?, ?, ?)");

// 1. two tabs of one browser: a hold still waiting for Stripe can't be taken over, and the visitor limit leaves it alone
$slotR = $at('+20 days');
db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, NULL, ?, ?, ?, ?)')
    ->execute([$slotR, time() + 31 * 60 + 120, 'whoR', 'browserR', 'n1']);
throws(fn() => release_own_hold('browserR', $slotR), 409, 'review 1: a second tab cannot replace a hold whose payment page is still being made');
ok((bool)db()->query("SELECT 1 FROM holds WHERE nonce = 'n1'")->fetchColumn(), 'review 1: and that hold stays');
foreach (['a', 'b', 'c'] as $i => $x) db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$at('+' . (21 + $i) . ' days'), null, time() + 31 * 60 + 120 + $i, 'whoR', 'b' . $x, 'm' . $x]);
cap_holds('whoR', time());
ok((int)db()->query("SELECT COUNT(*) FROM holds WHERE who = 'whoR'")->fetchColumn() === 4, 'review 1: the visitor limit never ends a hold whose request is still running');
db()->prepare('UPDATE holds SET expires_at = ? WHERE nonce = ?')->execute([time() + 31 * 60 + 120 - 120, 'n1']);   // made 2 minutes ago: dead
release_own_hold('browserR', $slotR);
ok(!db()->query("SELECT 1 FROM holds WHERE nonce = 'n1'")->fetchColumn(), 'review 1: a hold stuck without a Stripe page for a minute is let go');
db()->exec("DELETE FROM holds WHERE who = 'whoR'");
// the last line: a second payment for a time already booked is refunded, not booked
$slotD = $at('+25 days');
$add->execute(['cs_rv_first', $slotD, 'online', 'booked', 'ev1', null, null, time(), time()]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_second#' => fn() => [200, ['id' => 'cs_rv_second', 'payment_intent' => 'pi_2']],
    '#POST .*/v1/refunds#' => fn() => [200, ['id' => 're_double', 'status' => 'pending']]];
$dmeta = ['den' => substr($slotD, 0, 10), 'cas' => '10:00', 'forma' => 'online', 'email' => 'b@example.com', 'jmeno' => 'Petr Novák', 'telefon' => '', 'kopie' => site_copy()];
$d = book_paid(['id' => 'cs_rv_second', 'payment_status' => 'paid', 'metadata' => $dmeta]);
ok($d['status'] === 'cancelled' && $d['cancelled_by'] === 'double' && $d['refund_id'] === 're_double', 'review 1: a payment for a time already booked is refunded, not booked');
ok(booking_get('cs_rv_first')['status'] === 'booked' && !in_array('POST https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode(cfg()['calendar']) . '/events?conferenceDataVersion=1&sendUpdates=none', $CALLS, true),
    'review 1: the first booking stays, no second calendar event');
ok(!db()->query("SELECT 1 FROM mail_queue WHERE pay_id = 'cs_rv_second'")->fetchColumn(), 'review 1: the client and she are both told (nothing left in the queue)');
ok(str_contains(mail_client_double($d, prague($d['slot']))['text'], 'vrací celá'), 'review 1: the client e-mail says the money comes back');

// 2. Meet link still being made: no confirmation with a dead button; it goes once the link is there
$slotM = $at('+26 days');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, status, owner_mailed, created_at, updated_at) VALUES ('cs_rv_meet', ?, 'online', 'a@example.com', 'Jana Nováková', 'pending', 1, ?, ?)")
    ->execute([$slotM, time(), time()]);
$link = null;
$FAKE = ['#POST .*/freeBusy#' => fn() => [200, ['calendars' => array_fill_keys(array_merge(cfg()['busy_calendars'], [cfg()['calendar']]), ['busy' => []])]],
    '#POST .*/events\?#' => fn() => [200, ['id' => 'evMeet', 'conferenceData' => ['createRequest' => ['status' => ['statusCode' => 'pending']]]]],
    '#GET .*/events/evMeet#' => function () use (&$link) { return [200, ['id' => 'evMeet'] + ($link ? ['hangoutLink' => $link] : [])]; }];
$m1 = finish_booking('cs_rv_meet');
ok($m1['status'] === 'booked' && !$m1['meet'] && !$m1['mail_sent'], 'review 2: without the Meet link the confirmation waits');
ok(!str_contains(session_panel($m1, prague($m1['slot']), true), 'href="#"') && str_contains(session_panel($m1, prague($m1['slot']), true), MEET_LATER),
    'review 2: an e-mail without the link never has a button to "#"');
$link = 'https://meet.google.com/abc-defg-hij';
$m2 = finish_booking('cs_rv_meet');
ok($m2['meet'] === $link && $m2['mail_sent'] == 1, 'review 2: the safety net picks up the link and sends the confirmation with it');

// 3. refunds: Stripe's "failed" is a failure, and a later failure reaches her
$add->execute(['cs_rv_ref', $at('+27 days'), 'online', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_ref#' => fn() => [200, ['id' => 'cs_rv_ref', 'payment_intent' => 'pi_r']],
    '#POST .*/v1/refunds#' => fn() => [200, ['id' => 're_bad', 'status' => 'failed', 'failure_reason' => 'expired_or_canceled_card']]];
throws(fn() => cancel_booking('cs_rv_ref', null, true), 502, 'review 3: a refund Stripe answers with "failed" is not shown as refunded');
ok(booking_get('cs_rv_ref')['status'] === 'booked', 'review 3: and the booking stays');
$add->execute(['cs_rv_ref2', $at('+28 days'), 'online', 'cancelled', null, 're_later', 'pending', 0, 0]);
$FAKE = ['#GET .*/v1/refunds/re_later#' => fn() => [200, ['id' => 're_later', 'status' => 'failed', 'failure_reason' => 'lost_or_stolen_card']]];
$rf = refund_changed('re_later');
ok($rf['refund_status'] === 'failed' && !booking_view($rf)['refunded'] && booking_view($rf)['refund_failed'], 'review 3: a refund failing later (webhook) is recorded');
ok(str_contains(mail_owner_refund_failed($rf, prague($rf['slot']), owner_url('cs_rv_ref2'))['text'], 'nevrátily'), 'review 3: she gets an e-mail about it');
ok(refund_changed('re_later')['refund_status'] === 'failed' && (int)db()->query("SELECT COUNT(*) FROM mail_queue WHERE pay_id = 'cs_rv_ref2'")->fetchColumn() === 0, 'review 3: a repeated webhook changes nothing');
$FAKE = ['#GET .*/checkout/sessions/cs_rv_ref2#' => fn() => [200, ['id' => 'cs_rv_ref2', 'payment_intent' => 'pi_r2']],
    '#POST .*/v1/refunds#' => fn($m, $u, $b, $h) => [200, ['id' => in_array('Idempotency-Key: cancel-cs_rv_ref2-after-re_later', $h, true) ? 're_again' : 're_samekey', 'status' => 'pending']]];
ok(owner_cancel('cs_rv_ref2')['refund_id'] === 're_again', 'review 3: after a failed refund she can refund again (a new Stripe key)');

// 4. covered by .htaccess (needs Apache) and the command-line guard at the top of this file

// 5. moves: one at a time; a failed Google update undoes only its own move
$slotV = $at('+29 days');
$add->execute(['cs_rv_move', $slotV, 'online', 'booked', 'evMove', null, null, 0, 0]);
db()->prepare("UPDATE bookings SET lock_until = ? WHERE pay_id = 'cs_rv_move'")->execute([time() + 30]);
throws(fn() => owner_move('cs_rv_move', substr($at('+30 days'), 0, 10), '12:00'), 409, 'review 5: a second move while the first is talking to Google is refused');
throws(fn() => cancel_booking('cs_rv_move'), 409, 'review 5: so is a cancellation');
db()->exec("UPDATE bookings SET lock_until = NULL WHERE pay_id = 'cs_rv_move'");
$FAKE = ['#PATCH .*/events/evMove#' => fn() => [500, ['error' => 'boom']]];
throws(fn() => owner_move('cs_rv_move', substr($at('+30 days'), 0, 10), '12:00'), 502, 'review 5: Google fails the move');
$mv = booking_get('cs_rv_move');
ok($mv['slot'] === $slotV && !$mv['lock_until'], 'review 5: the booking is back on its time and unlocked');
$FAKE = ['#PATCH .*/events/evMove#' => fn() => [200, ['id' => 'evMove']]];
ok(owner_move('cs_rv_move', substr($at('+30 days'), 0, 10), '12:00')['slot'] !== $slotV && !booking_get('cs_rv_move')['lock_until'], 'review 5: a move that works unlocks');

// 6. cancellation: a calendar event Google failed to delete is retried; e-mails wait in the queue until sent
$add->execute(['cs_rv_can', $at('+31 days'), 'osobne', 'booked', 'evCan', null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_can#' => fn() => [200, ['id' => 'cs_rv_can', 'payment_intent' => 'pi_c']],
    '#POST .*/v1/refunds#' => fn() => [200, ['id' => 're_can', 'status' => 'succeeded']],
    '#DELETE .*/events/evCan#' => fn() => [503, ['error' => 'backend']]];
$cn = cancel_booking('cs_rv_can', null, true);
ok($cn['status'] === 'cancelled' && booking_get('cs_rv_can')['stale_event'] === 'evCan', 'review 6: a failed calendar delete is written down');
ok($cn['mailed'] === true, 'review 6: the page hears whether the client e-mail went');
$FAKE = ['#DELETE .*/events/evCan#' => fn() => [204, []]];
event_gone('cs_rv_can', 'evCan');
ok(booking_get('cs_rv_can')['stale_event'] === null, 'review 6: the next try deletes it and crosses it off');
ok(mail_queued('rv-bad-kind', 'cs_rv_can', 'no_such_kind') === false && (bool)db()->query("SELECT 1 FROM mail_queue WHERE name = 'rv-bad-kind'")->fetchColumn(),
    'review 6: an e-mail that could not go stays queued for the safety net');
db()->exec("DELETE FROM mail_queue WHERE name = 'rv-bad-kind'");

// --- 16 Nov review, the open findings: each with the same kind of made-up answers
// 7. Google made the event but the answer was lost: the retry finds it (same id), no second event
$slotE = $at('+32 days');
db()->prepare("INSERT INTO bookings (pay_id, slot, forma, email, name, status, owner_mailed, mail_sent, created_at, updated_at) VALUES ('cs_rv_ev', ?, 'osobne', NULL, 'Jana Nováková', 'failed', 1, 1, ?, ?)")
    ->execute([$slotE, time(), time()]);
$evId = gcal_event_id('cs_rv_ev');
$posts = 0;
$FAKE = ['#POST .*/freeBusy#' => fn() => [200, ['calendars' => array_fill_keys(array_merge(cfg()['busy_calendars'], [cfg()['calendar']]), ['busy' => []])]],
    '#POST .*/events\?#' => function ($m, $u, $b) use (&$posts, $evId) { $posts++; return json_decode($b, true)['id'] === $evId ? [409, ['error' => 'duplicate']] : [200, ['id' => 'wrong']]; },
    '#GET .*/events/' . $evId . '#' => fn() => [200, ['id' => $evId, 'status' => 'confirmed']]];
$e7 = finish_booking('cs_rv_ev');
ok($e7['status'] === 'booked' && $e7['event_id'] === $evId && $posts === 1, 'open 7: a retry after a lost answer takes the event Google already made');
ok(preg_match('/^[a-v0-9]{5,1024}$/', $evId) === 1 && $evId !== gcal_event_id('cs_rv_other'), 'open 7: the event id is one Google accepts, one per payment');

// 11. her deletion counts from when she deleted it, also when the sync comes late; a skipped event is read again
$slotS = (new DateTimeImmutable('+30 minutes'))->format(DATE_ATOM);
$add->execute(['cs_rv_del', $slotS, 'online', 'booked', 'evDel', null, null, 0, 0]);
$add->execute(['cs_rv_busy', $at('+33 days'), 'online', 'booked', 'evBusy', null, null, time(), time()]);   // the site just changed it
$late = time() + 3600;   // the sync runs an hour late, after the session's start
state_set('calendar_sync', (string)(time() - 60));
$FAKE = ['#GET .*/events\?#' => fn() => [200, ['items' => [
    ['id' => 'evDel', 'status' => 'cancelled', 'updated' => gmdate('Y-m-d\TH:i:s\Z', time() - 60)],
    ['id' => 'evBusy', 'status' => 'confirmed', 'updated' => gmdate('Y-m-d\TH:i:s\Z', time() - 10), 'start' => ['dateTime' => $at('+34 days')]]]]],
    '#POST .*/smtp#' => fn() => [200, []]];
db()->exec("UPDATE bookings SET updated_at = $late WHERE pay_id = 'cs_rv_busy'");
sync_calendar($late);
$d11 = booking_get('cs_rv_del');
ok($d11['status'] === 'cancelled' && (float)$d11['hours_before'] > 0, 'open 11: deleted before the start counts as before, though the sync came after it (' . $d11['hours_before'] . ' h)');
ok((int)state_get('calendar_sync') <= time() - 10, 'open 11: the next sync starts no later than the event it had to leave for later');
ok(booking_get('cs_rv_busy')['slot'] === $at('+33 days'), 'open 11: and that event was left alone this time');

// 5. the safety net reads every page of Stripe's list; a failed list does not stop the other retries
$pages = [];
$FAKE = ['#GET .*/checkout/sessions\?#' => function ($m, $u) use (&$pages) {
        $pages[] = $u;
        return str_contains($u, 'starting_after=cs_p1_last') ? [200, ['data' => [['id' => 'cs_p2']], 'has_more' => false]]
            : [200, ['data' => [['id' => 'cs_p1'], ['id' => 'cs_p1_last']], 'has_more' => true]];
    }];
ok(array_column(paid_sessions_recent(), 'id') === ['cs_p1', 'cs_p1_last', 'cs_p2'] && count($pages) === 2, 'open 5: both pages of the list are read');
db()->exec("UPDATE bookings SET stale_event = 'evStale' WHERE pay_id = 'cs_rv_can'");
$FAKE = ['#GET .*/checkout/sessions\?#' => fn() => [500, ['error' => 'down']], '#DELETE .*/events/evStale#' => fn() => [204, []],
    '#GET .*/events\?#' => fn() => [200, ['items' => []]], '#POST .*/freeBusy#' => fn() => [200, ['calendars' => []]]];
$threw = false;
try { sweep(); } catch (RuntimeException $e) { $threw = str_contains($e->getMessage(), 'Stripe list failed'); }
ok($threw && booking_get('cs_rv_can')['stale_event'] === null, 'open 5: Stripe down, the stale event is still deleted and the failure still reported');

// 3. Stripe made the refund but the answer was lost; the retry is refused: the refund found on Stripe counts
$add->execute(['cs_rv_lost', $at('+35 days'), 'osobne', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_lost#' => fn() => [200, ['id' => 'cs_rv_lost', 'payment_intent' => 'pi_lost', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn() => [400, ['error' => ['message' => 'Keys for idempotent requests can only be used with the same parameters']]],
    '#GET .*/v1/refunds\?payment_intent=pi_lost#' => fn() => [200, ['data' => [['id' => 're_lost', 'status' => 'succeeded', 'amount' => 110000]]]]];
$l3 = cancel_booking('cs_rv_lost', null, true);
ok($l3['status'] === 'cancelled' && $l3['refund_id'] === 're_lost', 'open 3: a refund that went through is found and the booking cancelled');
$add->execute(['cs_rv_lost2', $at('+36 days'), 'osobne', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_lost2#' => fn() => [200, ['id' => 'cs_rv_lost2', 'payment_intent' => 'pi_lost2']],
    '#POST .*/v1/refunds#' => fn() => throw new RuntimeException('HTTP POST failed: timeout'),
    '#GET .*/v1/refunds\?payment_intent=pi_lost2#' => fn() => [200, ['data' => [['id' => 're_old', 'status' => 'failed']]]]];
throws(fn() => cancel_booking('cs_rv_lost2', null, true), 502, 'open 3: no answer and no refund on Stripe (only a failed one): nothing is cancelled');
ok(booking_get('cs_rv_lost2')['status'] === 'booked', 'open 3: the time stays booked');
$keys = [];
$FAKE = ['#GET .*/checkout/sessions/cs_rv_lost2#' => fn() => [200, ['id' => 'cs_rv_lost2', 'payment_intent' => 'pi_lost2']],
    '#POST .*/v1/refunds#' => function ($m, $u, $b, $h) use (&$keys) { $keys[] = $b; return [200, ['id' => 're_ok', 'status' => 'pending']]; }];
cancel_booking('cs_rv_lost2', new DateTimeImmutable('+1 hour'), true);
ok(count($keys) === 1 && !preg_match('/\d+\+?h/', urldecode($keys[0])), 'open 3: the refund sends the same parameters at any hour (no hours in the reason)');

// 13 + 18. an old payment with no booking is never brought back; an unmarked payment belongs to the lab ("khg")
$old = ['id' => 'cs_rv_old', 'payment_status' => 'paid', 'created' => time() - 400 * 86400,
    'metadata' => ['den' => substr($at('+37 days'), 0, 10), 'cas' => '10:00', 'forma' => 'online', 'email' => 'a@b.cz', 'jmeno' => 'Jana Nováková', 'telefon' => '', 'kopie' => site_copy()]];
ok(book_paid($old) === null && booking_get('cs_rv_old') === null, 'open 13: a year-old payment whose booking was deleted is not booked again');
$unmarked = $old; unset($unmarked['metadata']['kopie']); $unmarked['created'] = time(); $unmarked['id'] = 'cs_rv_unmarked';
ok(session_copy($unmarked) === 'khg' && book_paid($unmarked) === null, 'open 18: an unmarked payment is the lab\'s, never booked by another copy');

// 14 + 15. several PHP processes at once: a fresh deploy's new columns, and sign-ins with the right password
$child = function (string $dir, string $code): array {
    $boot = 'putenv(' . var_export("KHG_CONFIG_DIR=$dir", true) . ');'
        . 'foreach ([\'config\',\'stripe\',\'google\',\'store\',\'mail\',\'booking\',\'slots\',\'hours\',\'owner\'] as $f) require ' . var_export(dirname(__DIR__) . '/src/', true) . ' . $f . \'.php\';';
    $procs = [];
    for ($i = 0; $i < 6; $i++) {
        $procs[] = proc_open([PHP_BINARY, '-r', $boot . 'try {' . $code . ' echo "OK"; } catch (Throwable $e) { echo $e->getMessage(); }'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $outs[] = $pipes[1];
    }
    $res = array_map(fn($p) => trim(preg_replace('/^\S+ OWNER_LOGIN .*$/m', '', stream_get_contents($p))), $outs);
    foreach ($procs as $p) proc_close($p);
    return $res;
};
$mig = "$tmp/migrate";
mkdir($mig, 0700);
foreach (['stripe-test.env', 'booking-secret', 'owner-password', 'booking.env'] as $f) copy("$tmp/$f", "$mig/$f");
$o = new PDO("sqlite:$mig/booking.sqlite");
$o->exec('PRAGMA journal_mode = WAL');
$o->exec('CREATE TABLE holds (slot TEXT PRIMARY KEY, pay_id TEXT, expires_at INTEGER NOT NULL)');
$o->exec('CREATE TABLE bookings (pay_id TEXT PRIMARY KEY, slot TEXT NOT NULL, forma TEXT NOT NULL, email TEXT, phone TEXT, name TEXT, status TEXT NOT NULL, event_id TEXT, meet TEXT, clash INTEGER DEFAULT 0, mail_sent INTEGER DEFAULT 0, refund_id TEXT, refund_status TEXT, hours_before REAL, error TEXT, created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL)');
$o->exec("INSERT INTO bookings (pay_id, slot, forma, status, created_at, updated_at) VALUES ('cs_old', '2026-01-01T10:00:00+01:00', 'online', 'booked', 0, 0)");
$o = null;
$r14 = $child($mig, 'db();');
ok($r14 === array_fill(0, 6, 'OK'), 'open 14: six requests at once on an old database all start (' . implode(' | ', array_unique($r14)) . ')');
$o = new PDO("sqlite:$mig/booking.sqlite");
ok((int)$o->query("SELECT owner_mailed FROM bookings WHERE pay_id = 'cs_old'")->fetchColumn() === 1, 'open 14: existing bookings count as announced, once');
$o = null;
$r15 = $child($tmp, 'owner_login("spravne-heslo-42", "Chrome/1 Windows", "w" . getmypid());');
ok($r15 === array_fill(0, 6, 'OK'), 'open 15: six sign-ins at once with the right password all go in (' . implode(' | ', array_unique($r15)) . ')');
// --- 17 Nov review
// 1. a slow "back from Stripe" release must not delete the newer hold that replaced it for the same time
$slotH = $at('+41 days');
db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')->execute([$slotH, 'cs_hA', time() + 1800, 'whoH', 'brH', 'hA']);
$FAKE = ['#POST .*/checkout/sessions/cs_hA/expire#' => function () use ($slotH) {
    // while Stripe answers, the same browser's other tab replaces the hold with a new payment page
    db()->prepare("DELETE FROM holds WHERE nonce = 'hA'")->execute();
    db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')->execute([$slotH, 'cs_hB', time() + 1800, 'whoH', 'brH', 'hB']);
    return [200, ['status' => 'expired']];
}];
release_browser_holds('brH', 'cs_hA');
ok((bool)db()->query("SELECT 1 FROM holds WHERE pay_id = 'cs_hB'")->fetchColumn(), 'review 17/1: a slow release leaves the newer hold for the same time alone');
db()->exec("DELETE FROM holds WHERE who = 'whoH'");
foreach (['a', 'b', 'c'] as $i => $x) db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')
    ->execute([$at('+' . (42 + $i) . ' days'), 'cs_c' . $x, time() + 1800 + $i, 'whoC', 'brC', 'c' . $x]);
$FAKE = ['#POST .*/checkout/sessions/cs_ca/expire#' => function () {
    db()->prepare("UPDATE holds SET pay_id = 'cs_cnew', nonce = 'cnew' WHERE nonce = 'ca'")->execute();
    return [200, ['status' => 'expired']];
}];
cap_holds('whoC', time());
ok((bool)db()->query("SELECT 1 FROM holds WHERE pay_id = 'cs_cnew'")->fetchColumn(), 'review 17/1: the visitor limit leaves a hold that was replaced meanwhile alone');
db()->exec("DELETE FROM holds WHERE who = 'whoC'");

// 3a. a partial refund she made in Stripe is never taken as the full one
$add->execute(['cs_rv_part', $at('+46 days'), 'osobne', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_part#' => fn() => [200, ['id' => 'cs_rv_part', 'payment_intent' => 'pi_part', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn() => [400, ['error' => ['message' => 'Refund amount is greater than unrefunded amount on charge']]],
    '#GET .*/v1/refunds\?payment_intent=pi_part#' => fn() => [200, ['data' => [['id' => 're_part', 'status' => 'succeeded', 'amount' => 12000]]]]];
throws(fn() => cancel_booking('cs_rv_part', null, true), 502, 'review 17/3a: a 120 Kč partial refund is not the refund of 1 100 Kč');
ok(booking_get('cs_rv_part')['status'] === 'booked' && booking_get('cs_rv_part')['refund_id'] === null, 'review 17/3a: the booking stays, no refund recorded');
throws(fn() => owner_cancel('cs_rv_part'), 502, 'review 17/3a: her cancel does not take the partial refund either');
$FAKE['#GET .*/v1/refunds\?payment_intent=pi_part#'] = fn() => [200, ['data' => [['id' => 're_full', 'status' => 'pending', 'amount' => 110000]]]];
ok(owner_cancel('cs_rv_part')['refund_id'] === 're_full', 'review 17/3a: a full refund found on Stripe still counts');

// 3c. two cancellations either side of 36 hours: the refund that went through stays on the record, the client is told
$add->execute(['cs_rv_race', $at('+47 days'), 'osobne', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_race#' => fn() => [200, ['id' => 'cs_rv_race', 'payment_intent' => 'pi_race', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => function () {
        // meanwhile the other request (just after the 36 hours, so without a refund) cancels first
        booking_update('cs_rv_race', ['status' => 'cancelled', 'cancelled_by' => 'client', 'hours_before' => 35.9]);
        return [200, ['id' => 're_race', 'status' => 'pending']];
    }];
$rc = cancel_booking('cs_rv_race', null, true);
ok($rc['status'] === 'cancelled' && $rc['refund_id'] === 're_race' && $rc['refund_status'] === 'pending', 'review 17/3c: the refund made by the slower request is recorded');
ok(str_contains((string)file_get_contents(cfg()['dir'] . '/booking.log'), 'REFUND_AFTER_CANCEL {"pay":"cs_rv_race"'), 'review 17/3c: and the client gets the refund e-mail');

// 3b. the safety net asks Stripe about refunds still on their way: a failure reaches her without the webhook
$add->execute(['cs_rv_chk', $at('+48 days'), 'osobne', 'cancelled', null, 're_chk', 'pending', 0, 0]);
ok(in_array('re_chk', refunds_to_check(), true) && !in_array('re_lost', refunds_to_check(), true), 'review 17/3b: pending refunds are rechecked, finished ones are not');
$FAKE = ['#GET .*/checkout/sessions\?#' => fn() => [200, ['data' => [], 'has_more' => false]],
    '#GET .*/v1/refunds/re_chk#' => fn() => [200, ['id' => 're_chk', 'status' => 'failed', 'failure_reason' => 'expired_or_canceled_card']],
    '#GET .*/events\?#' => fn() => [200, ['items' => []]], '#POST .*/freeBusy#' => fn() => [200, ['calendars' => []]]];
$sw = sweep();
ok(booking_get('cs_rv_chk')['refund_status'] === 'failed' && in_array('cs_rv_chk', $sw['refund_failed'] ?? [], true), 'review 17/3b: a refund that failed is found by the safety net');
ok((int)db()->query("SELECT COUNT(*) FROM mail_queue WHERE pay_id = 'cs_rv_chk'")->fetchColumn() === 0 && !in_array('re_chk', refunds_to_check(), true), 'review 17/3b: she got the e-mail, and it is not asked again');

// 6. the safety net looks back 3 days, as long as Stripe retries a webhook
$gte = null;
$FAKE = ['#GET .*/checkout/sessions\?#' => function ($m, $u) use (&$gte) { parse_str((string)parse_url($u, PHP_URL_QUERY), $q); $gte = (int)$q['created']['gte']; return [200, ['data' => [], 'has_more' => false]]; }];
paid_sessions_recent();
ok($gte !== null && abs($gte - (time() - 3 * 86400)) < 10, 'review 17/6: a payment made 25 hours ago is still looked for');

// 2b. Google moved the event but its answer was lost: the booking keeps the new time
$evPath = '/calendars/' . rawurlencode(cfg()['calendar']) . '/events/';
$moveCase = function (string $pay, string $evId, callable $patch, callable $get) use ($add, $at, &$FAKE) {
    $add->execute([$pay, $at('+50 days'), 'osobne', 'booked', $evId, null, null, 0, 0]);
    $FAKE = ['#PATCH .*/events/' . $evId . '#' => $patch, '#GET .*/events/' . $evId . '#' => $get];
    $new = (new DateTimeImmutable($at('+50 days')))->setTime(14, 0);
    try { $r = owner_move($pay, $new->format('Y-m-d'), '14:00'); } catch (ClientError $e) { $r = $e; }
    return [$r, booking_get($pay), $new];
};
$newIso = fn(DateTimeImmutable $d) => ['start' => ['dateTime' => $d->format(DATE_ATOM)]];
[$r, $bk, $new] = $moveCase('cs_mv_landed', 'evL', fn() => throw new RuntimeException('timeout'), fn() => [200, ['start' => ['dateTime' => (new DateTimeImmutable($at('+50 days')))->setTime(14, 0)->format(DATE_ATOM)]]]);
ok(is_array($r) && $bk['slot'] === $new->format(DATE_ATOM) && $bk['lock_until'] === null, 'review 17/2b: Google moved it, its answer was lost: the booking keeps the new time');
db()->exec("DELETE FROM bookings WHERE pay_id = 'cs_mv_landed'");
[$r, $bk] = $moveCase('cs_mv_not', 'evN', fn() => [500, ['error' => 'x']], fn() => [200, ['start' => ['dateTime' => $at('+50 days')]]]);
ok($r instanceof ClientError && $r->getCode() === 502 && $bk['slot'] === $at('+50 days'), 'review 17/2b: Google did not move it: back to the old time, as before');
db()->exec("DELETE FROM bookings WHERE pay_id = 'cs_mv_not'");
[$r, $bk, $new] = $moveCase('cs_mv_sold', 'evS', function () use ($add, $at) {
    $add->execute(['cs_mv_other', $at('+50 days'), 'osobne', 'booked', 'evO', null, null, 0, 0]);   // someone paid for the old time meanwhile
    throw new RuntimeException('timeout');
}, fn() => [503, ['error' => 'down']]);
ok(is_array($r) && $bk['slot'] === $new->format(DATE_ATOM), 'review 17/2b: Google unreadable and the old time sold meanwhile: the booking never goes back onto it');

// --- 17 Nov review, second round
// 1b. Google unreadable and someone is on Stripe's page for the old time: the move is kept, not rolled back onto it
db()->exec("DELETE FROM bookings WHERE pay_id IN ('cs_mv_sold', 'cs_mv_other')");
[$r, $bk, $new] = $moveCase('cs_mv_held', 'evH', function () use ($at) {
    db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')->execute([$at('+50 days'), 'cs_paying', time() + 1800, 'whoP', 'brP', 'nP']);
    throw new RuntimeException('timeout');
}, fn() => [503, ['error' => 'down']]);
ok(is_array($r) && $bk['slot'] === $new->format(DATE_ATOM), 'review 17b/1b: Google unreadable and the old time being paid for: the move is kept');
db()->exec("DELETE FROM holds WHERE who = 'whoP'"); db()->exec("DELETE FROM bookings WHERE pay_id = 'cs_mv_held'");

// 5. her refunds in parts that together return the whole payment count as the refund
$add->execute(['cs_rv_split', $at('+51 days'), 'osobne', 'booked', null, null, null, 0, 0]);
$FAKE = ['#GET .*/checkout/sessions/cs_rv_split#' => fn() => [200, ['id' => 'cs_rv_split', 'payment_intent' => 'pi_split', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn() => [400, ['error' => ['message' => 'Charge has already been refunded']]],
    '#GET .*/v1/refunds\?payment_intent=pi_split#' => fn() => [200, ['data' => [['id' => 're_s2', 'status' => 'succeeded', 'amount' => 50000],
        ['id' => 're_s1', 'status' => 'succeeded', 'amount' => 60000]]]]];
$sp = cancel_booking('cs_rv_split', null, true);
ok($sp['status'] === 'cancelled' && $sp['refund_id'] === 're_s2', 'review 17b/5: two refunds of 600 + 500 Kč count as the full refund');

// 2. a payment booked only after its session started is refunded, never confirmed
$y = (new DateTimeImmutable('-1 day'))->setTime(10, 0);
$late = ['id' => 'cs_rv_missed', 'payment_status' => 'paid', 'created' => time() - 2 * 86400,
    'metadata' => ['den' => $y->format('Y-m-d'), 'cas' => '10:00', 'forma' => 'online', 'email' => 'a@b.cz', 'jmeno' => 'Jana Nováková', 'telefon' => '', 'kopie' => site_copy()]];
$CALLS = [];
$FAKE = ['#GET .*/checkout/sessions/cs_rv_missed#' => fn() => [200, ['id' => 'cs_rv_missed', 'payment_intent' => 'pi_missed', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn($m, $u, $b) => [200, ['id' => 're_missed', 'status' => 'pending', 'meta' => $b]]];
$ms = book_paid($late);
ok($ms['status'] === 'cancelled' && $ms['cancelled_by'] === 'missed' && $ms['refund_id'] === 're_missed' && !$ms['event_id'], 'review 17b/2: a session already past is refunded, not booked');
ok(!array_filter($CALLS, fn($c) => str_contains($c, 'googleapis.com/calendar')) && (int)db()->query("SELECT COUNT(*) FROM mail_queue WHERE pay_id = 'cs_rv_missed'")->fetchColumn() === 0,
    'review 17b/2: no calendar event; the client and she got their e-mails');
$cm = mail_client_double($ms, $y);
$om = mail_owner_double($ms, $y, true);
ok(str_contains($cm['subject'], 'se nedokončila') && str_contains($cm['text'], 'nezapsala včas') && str_contains($om['text'], 'až po začátku sezení'), 'review 17b/2: the e-mails say what happened');
$late2 = $late; $late2['id'] = 'cs_rv_missed2';
$FAKE = ['#GET .*/checkout/sessions/cs_rv_missed2#' => fn() => [200, ['id' => 'cs_rv_missed2', 'payment_intent' => 'pi_missed2', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn() => [500, ['error' => ['message' => 'down']]], '#GET .*/v1/refunds\?#' => fn() => [200, ['data' => []]]];
ok(book_paid($late2)['refund_id'] === null, 'review 17b/2: Stripe down: not refunded yet');
$FAKE = ['#GET .*/checkout/sessions/cs_rv_missed2#' => fn() => [200, ['id' => 'cs_rv_missed2', 'payment_intent' => 'pi_missed2', 'amount_total' => 110000]],
    '#POST .*/v1/refunds#' => fn() => [200, ['id' => 're_missed2', 'status' => 'pending']],
    '#GET .*/checkout/sessions\?#' => fn() => [200, ['data' => [], 'has_more' => false]],
    '#GET .*/events\?#' => fn() => [200, ['items' => []]], '#POST .*/freeBusy#' => fn() => [200, ['calendars' => []]]];
$sw2 = sweep();
ok(booking_get('cs_rv_missed2')['refund_id'] === 're_missed2' && in_array('cs_rv_missed2', $sw2['double_refunded'] ?? [], true), 'review 17b/2: the safety net refunds it later');

// 5a. the live site refuses Stripe test keys
$lv = "$tmp/live";
mkdir($lv, 0700);
foreach (['booking-secret', 'owner-password'] as $f) copy("$tmp/$f", "$lv/$f");
file_put_contents("$lv/booking.env", "BOOKING_MODE=live\nSITE_URL=https://example.invalid\n");
file_put_contents("$lv/stripe-test.env", "STRIPE_SECRET_KEY=sk_test_dummy\n");
ok(in_array('Live mode refuses Stripe keys that are not live keys.', $child($lv, 'cfg();'), true), 'review 17/5a: live mode with a test key refuses to start');
file_put_contents("$lv/stripe-test.env", "STRIPE_SECRET_KEY=sk_live_dummy\n");
ok($child($lv, 'cfg();')[0] === 'OK', 'review 17/5a: live mode with a live key starts');
file_put_contents("$lv/booking.env", "BOOKING_MODE=live\n");
ok(in_array('Live mode needs SITE_URL=https://… in booking.env.', $child($lv, 'cfg();'), true), 'run 16/2: live mode without SITE_URL refuses to start');
file_put_contents("$lv/booking.env", "BOOKING_MODE=live\nSITE_URL=http://khgporadenstvi.cz\n");
ok(in_array('Live mode needs SITE_URL=https://… in booking.env.', $child($lv, 'cfg();'), true), 'run 16/2: live mode refuses a plain http SITE_URL');

// 16 Nov test run, 4: phone numbers in one shape
foreach (['600000000', ' 600 000 000 ', '00420600000000', '420 600-000-000', '+420600000000', '+421 905 123 456'] as $in) {
    ok(in_array(phone_pretty($in), ['+420 600 000 000', '+421 905 123 456'], true), "run 16/4: phone '$in' -> " . phone_pretty($in));
}
ok(phone_pretty('+44 20 7946 0958') === '+44 20 7946 0958', 'run 16/4: a foreign number stays as typed');

// 16 Nov test run, 1: the client is told when the therapist moved or cancelled the session
$tb = ['pay_id' => 'cs_run16', 'forma' => 'online', 'email' => 'k@example.com', 'meet' => 'https://meet.google.com/x', 'moves' => 1, 'name' => 'K'];
$t0 = new DateTimeImmutable('2030-03-04 10:00', new DateTimeZone('Europe/Prague'));
$t1 = $t0->modify('+1 day');
ok(str_contains(mail_build($tb, $t1, 'u', $t0, false)['text'], 'Termín přesunula ' . SENDER), 'run 16/1: her move names her');
ok(!str_contains(mail_build($tb, $t1, 'u', $t0, true)['text'], 'přesunula'), 'run 16/1: the client\'s own move does not');
ok(str_contains(mail_client_cancelled($tb, $t0, false, true, true)['text'], 'Sezení zrušila ' . SENDER), 'run 16/1: her cancel names her');
ok(!str_contains(mail_client_cancelled($tb, $t0, false, true)['text'], 'zrušila'), 'run 16/1: the client\'s own cancel does not');

// --- review 17 Nov (another model's list): e-mails that must not get lost, sent twice, or retried for ever
$SENT = [];
$smtpDown = function () { throw new RuntimeException('SMTP connect smtp-relay.gmail.com:587 failed: Connection timed out'); };
$smtpOk = function (string $name, array $m) use (&$SENT) { $SENT[] = $name; };
$sentOnce = function (string $name) use (&$SENT) { return count(array_keys($SENT, $name, true)); };
$queued = fn(string $like) => db()->query("SELECT name, kind FROM mail_queue WHERE name LIKE " . db()->quote($like))->fetchAll();
state_set('mail_paused_until', '0');

// 1. Google's "come back later" answers pause sending too
ok(smtp_backoff('SMTP: expected 235, got 454 4.7.0 Too many login attempts, please try again later.') > 0, 'r17/1: Google 454 "too many login attempts" pauses sending');
ok(smtp_backoff('SMTP: expected 250, got 421 4.7.0 Try again later, closing connection.') === MAIL_BACKOFF_MINUTES, 'r17/1: Google 421 "try again later" pauses for a quarter of an hour');
ok(smtp_backoff('SMTP: expected 250, got 550 5.4.5 Daily SMTP relay limit exceeded for customer.') === MAIL_PAUSE_MINUTES, 'r17/1: the relay\'s daily limit pauses for an hour');
ok(smtp_backoff('SMTP: expected 235, got 535 5.7.8 Username and Password not accepted') === 0, 'r17/1: a wrong password does not pause');
ok(smtp_backoff('SMTP connect smtp-relay.gmail.com:587 failed: Connection timed out') === 0, 'r17/1: a server not answering does not pause');
ok(smtp_backoff('SMTP: expected 250, got 550 5.1.1 The email account that you tried to reach does not exist.') === 0, 'r17/1: a dead address does not pause');
$GLOBALS['SMTP_FAKE'] = function () { throw new SmtpRejected('SMTP: expected 250, got 550 5.4.5 Daily SMTP relay limit exceeded'); };
try { mail_send('r17-limit', ['to' => 'x@example.com', 'subject' => 's', 'text' => 't', 'html' => 'h', 'ics' => null]); } catch (Throwable $e) { $limitErr = $e; }
ok(isset($limitErr) && !$limitErr instanceof SmtpRejected && mail_paused() > time(), 'r17/1: a limit refused at RCPT pauses and is not taken as a dead address');
state_set('mail_paused_until', '0');

// 7. her deletion in the calendar: the e-mail to her is queued, so a mail outage can't swallow it
$add->execute(['cs_r17_del', $at('+40 days'), 'online', 'booked', 'evR17Del', null, null, 0, 0]);
state_set('calendar_sync', (string)(time() - 60));
$FAKE = ['#GET .*/events\?#' => fn() => [200, ['items' => [['id' => 'evR17Del', 'status' => 'cancelled', 'updated' => gmdate('Y-m-d\TH:i:s\Z', time() - 30)]]]]];
$GLOBALS['SMTP_FAKE'] = $smtpDown;
sync_calendar();
ok(booking_get('cs_r17_del')['status'] === 'cancelled' && count($queued('cs_r17_del-deleted')) === 1, 'r17/7: deleted in the calendar with the mail down: her e-mail waits in the queue');
$GLOBALS['SMTP_FAKE'] = $smtpOk;
ok(mail_queue_send('cs_r17_del-deleted', 'cs_r17_del', 'owner_deleted') && $sentOnce('cs_r17_del-deleted') === 1 && !$queued('cs_r17_del-deleted'), 'r17/7: and goes once the mail works');
db()->exec("UPDATE bookings SET refund_id = 're_r17', refund_status = 'pending' WHERE pay_id = 'cs_r17_del'");
ok(queued_mail(booking_get('cs_r17_del'), 'owner_deleted') === null, 'r17/7: once she has refunded it, a late copy is not sent any more');

// 5. the client's move: her note is queued too, and keeps the old time
$add->execute(['cs_r17_mv', $at('+41 days'), 'online', 'booked', 'evR17Mv', null, null, 0, 0]);
db()->exec("UPDATE bookings SET meet = 'https://meet.google.com/abc-defg-hij', mail_sent = 0, moves = 1, client_moves = 1 WHERE pay_id = 'cs_r17_mv'");
$GLOBALS['SMTP_FAKE'] = $smtpDown;
$oldMv = prague($at('+40 days'));
moved_mails(booking_get('cs_r17_mv'), $oldMv, 'client');
$qm = $queued('cs_r17_mv-moved-%');
ok(count($qm) === 1 && $qm[0]['kind'] === 'owner_moved|' . $oldMv->format(DATE_ATOM), 'r17/5: the client moved it with the mail down: her note waits in the queue');
ok(str_contains(queued_mail(booking_get('cs_r17_mv'), $qm[0]['kind'])['text'], 'Původně ' . cz_at($oldMv)), 'r17/5: and still names the old time when it goes');
ok(!booking_get('cs_r17_mv')['mail_try'] && !booking_get('cs_r17_mv')['mail_sent'], 'r17/5: the client\'s failed e-mail is left for the safety net');

// 3. one sender per e-mail
$GLOBALS['SMTP_FAKE'] = $smtpOk;
db()->exec("UPDATE bookings SET mail_try = " . time() . " WHERE pay_id = 'cs_r17_mv'");   // another request is sending it right now
db()->exec("UPDATE bookings SET status = 'booked', owner_mailed = 1 WHERE pay_id = 'cs_r17_mv'");
$FAKE = ['#GET .*/events/evR17Mv#' => fn() => [200, ['id' => 'evR17Mv', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']]];
finish_booking('cs_r17_mv');
ok($sentOnce('cs_r17_mv') === 0, 'r17/3: while another request is sending the confirmation, the safety net does not send it too');
db()->exec("UPDATE bookings SET mail_try = " . (time() - MAIL_CLAIM_SECONDS - 5) . " WHERE pay_id = 'cs_r17_mv'");
finish_booking('cs_r17_mv');
ok($sentOnce('cs_r17_mv') === 1 && booking_get('cs_r17_mv')['mail_sent'] == 1 && !booking_get('cs_r17_mv')['mail_try'], 'r17/3: a sender that died is taken over after a while, and it goes once');
finish_booking('cs_r17_mv');
ok($sentOnce('cs_r17_mv') === 1, 'r17/3: a second run after it went sends nothing');
db()->prepare('INSERT INTO mail_queue (name, pay_id, kind, created_at, tried_at) VALUES (?, ?, ?, ?, ?)')->execute(['cs_r17_mv-q', 'cs_r17_mv', 'owner_cancelled', time(), time()]);
ok(!mail_queue_send('cs_r17_mv-q', 'cs_r17_mv', 'owner_cancelled') && $sentOnce('cs_r17_mv-q') === 0, 'r17/3: a queued e-mail another request is sending is not sent twice');
ok(is_resource($lk = fopen($tmp . '/cron.lock', 'c')) && flock($lk, LOCK_EX | LOCK_NB)
    && str_contains(implode("\n", $child = (function () use ($tmp) { exec('KHG_CONFIG_DIR=' . escapeshellarg($tmp) . ' php ' . escapeshellarg(__DIR__ . '/../cron.php'), $o); return $o; })()), 'previous run still going'),
    'r17/3: a cron run while the previous one still runs does nothing');
flock($lk, LOCK_UN);

// 4. a dead address: no retry for ever, and she is told
$add->execute(['cs_r17_dead', $at('+42 days'), 'online', 'booked', 'evR17Dead', null, null, 0, 0]);
db()->exec("UPDATE bookings SET meet = 'https://meet.google.com/abc-defg-hij', owner_mailed = 1, phone = '+420 600 000 000' WHERE pay_id = 'cs_r17_dead'");
$GLOBALS['SMTP_FAKE'] = function (string $name, array $m) use (&$SENT) {
    if ($m['to'] === 'a@example.com') throw new SmtpRejected('SMTP: expected 250, got 550 5.1.1 The email account that you tried to reach does not exist.');
    $SENT[] = $name;
};
finish_booking('cs_r17_dead');
$dd = booking_get('cs_r17_dead');
ok($dd['mail_dead'] == 1 && !$dd['mail_sent'] && !$dd['mail_try'], 'r17/4: an address refused for good is marked, not retried');
ok($sentOnce('cs_r17_dead-undeliverable-confirmation') === 1, 'r17/4: she gets "E-mail klientovi nedorazil"');
ok(str_contains(mail_owner_undeliverable($dd, prague($dd['slot']), 'u', 'confirmation')['text'], 'potvrzení rezervace'), 'r17/4: naming which e-mail it was');
$s17 = db()->prepare("SELECT 1 FROM bookings WHERE pay_id = 'cs_r17_dead' AND status = 'booked' AND mail_sent = 0 AND email IS NOT NULL AND COALESCE(mail_dead, 0) = 0");
$s17->execute();
ok(!$s17->fetchColumn(), 'r17/4: the safety net no longer picks it up');
db()->prepare('INSERT INTO mail_queue (name, pay_id, kind, created_at) VALUES (?, ?, ?, ?)')->execute(['cs_r17_dead-old', 'cs_r17_dead', 'owner_cancelled', time() - 4 * 86400]);
$GLOBALS['SMTP_FAKE'] = $smtpDown;
ok(!mail_queue_send('cs_r17_dead-old', 'cs_r17_dead', 'owner_cancelled') && !$queued('cs_r17_dead-old'), 'r17/4: a queued e-mail failing for over ' . MAIL_GIVE_UP_DAYS . ' days is given up');
db()->prepare('INSERT INTO mail_queue (name, pay_id, kind, created_at) VALUES (?, ?, ?, ?)')->execute(['cs_r17_dead-new', 'cs_r17_dead', 'owner_cancelled', time()]);
ok(!mail_queue_send('cs_r17_dead-new', 'cs_r17_dead', 'owner_cancelled') && count($queued('cs_r17_dead-new')) === 1, 'r17/4: a fresh one is kept for the next try');
db()->exec("DELETE FROM mail_queue WHERE pay_id = 'cs_r17_dead'");

// 2. a cancellation e-mail that goes after Stripe refused the refund promises nothing
$rb = ['pay_id' => 'cs_r17_rf', 'forma' => 'online', 'email' => 'k@example.com', 'name' => 'K', 'slot' => $at('+43 days'), 'status' => 'cancelled',
    'cancelled_by' => 'owner', 'refund_id' => 're_x', 'refund_status' => 'failed'];
foreach (['client_cancel', 'client_refund', 'client_cancelled'] as $k) {
    $txt = queued_mail($rb, $k)['text'];
    ok(str_contains($txt, 'zdrželo') && !str_contains($txt, 'bude vrácena') && !str_contains($txt, 'nevrací'), "r17/2: '$k' after a failed refund says it is delayed");
}
ok(str_contains(queued_mail(['refund_status' => 'pending'] + $rb, 'client_cancel')['text'], 'bude vrácena'), 'r17/2: with the refund on its way it says the money comes back');

// 6. cancelling again: nothing sent now, and the answer says so
$again = cancel_booking('cs_r17_del');
ok(($again['already'] ?? false) === true && $again['mailed'] === false, 'r17/6: a second cancel says it was cancelled already and nothing was sent');

// 9. the cancel page knows who cancelled
ok(booking_view(booking_get('cs_r17_del'))['cancelled_by'] === 'calendar' && booking_view(booking_get('cs_rv_move'))['cancelled_by'] === null, 'r17/9: the cancel page is told who cancelled');

// 12. the visitor limit never ends a payment in the middle of its bank confirmation
db()->exec("DELETE FROM holds WHERE who = 'whoP'");
foreach (['p1' => 'requires_action', 'p2' => 'requires_payment_method', 'p3' => null] as $pid => $st)
    db()->prepare('INSERT INTO holds (slot, pay_id, expires_at, who, browser, nonce) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$at('+' . (50 + (int)$pid[1]) . ' days'), 'cs_test_' . $pid, time() + 31 * 60 + 120 + (int)$pid[1], 'whoP', 'br' . $pid, 'n' . $pid]);
$CALLS = [];
$FAKE = ['#GET .*/checkout/sessions/cs_test_p1\?#' => fn() => [200, ['id' => 'cs_test_p1', 'payment_intent' => ['status' => 'requires_action']]],
    '#GET .*/checkout/sessions/cs_test_p2\?#' => fn() => [200, ['id' => 'cs_test_p2', 'payment_intent' => ['status' => 'requires_payment_method']]],
    '#POST .*/checkout/sessions/cs_test_p\d/expire#' => fn() => [200, ['status' => 'expired']]];
cap_holds('whoP', time());
$left = db()->query("SELECT pay_id FROM holds WHERE who = 'whoP' ORDER BY expires_at")->fetchAll(PDO::FETCH_COLUMN);
ok($left === ['cs_test_p1', 'cs_test_p2', 'cs_test_p3'] && !preg_grep('#cs_test_p1/expire#', $CALLS), 'r17/12: the oldest payment, in 3-D Secure, is left alone (' . implode(',', $left) . ')');
db()->exec("UPDATE holds SET expires_at = expires_at - 10 WHERE pay_id = 'cs_test_p2'");   // now the oldest
cap_holds('whoP', time());
ok(!in_array('cs_test_p2', db()->query("SELECT pay_id FROM holds WHERE who = 'whoP'")->fetchAll(PDO::FETCH_COLUMN), true), 'r17/12: one not being paid is still freed as before');
db()->exec("DELETE FROM holds WHERE who = 'whoP'");
unset($GLOBALS['SMTP_FAKE']);

// --- 16 Nov (owner): the event and her e-mail like Google's booking pages
$gb = ['pay_id' => 'cs_g1', 'forma' => 'osobne', 'name' => 'Jana <b>Nováková</b>', 'email' => 'j@example.com', 'phone' => '+420 600 000 000',
    'note' => "Úzkosti\npřed zkouškami", 'slot' => '2030-03-04T10:00:00+01:00', 'event_id' => 'khgabc123', 'meet' => null, 'moves' => 0];
ok(event_title($gb) === 'Terapie osobně (Jana <b>Nováková</b>)' && event_title(['forma' => 'online'] + $gb) === 'Terapie online (Jana <b>Nováková</b>)', 'google: event title "Terapie osobně (Jméno)"');
$gd = event_description($gb);
ok(str_contains($gd, '<b>Rezervoval(a)</b><br>Jana &lt;b&gt;Nováková&lt;/b&gt;<br>j@example.com<br>+420 600 000 000'), 'google: "Rezervoval(a)" with name, e-mail and phone, escaped');
ok(str_contains($gd, '<b>S čím přicházíte?</b><br>Úzkosti<br>před zkouškami') && str_contains($gd, "1\u{00A0}100,00\u{00A0}Kč") && str_contains($gd, 'zvoňte až v čase'), 'google: the note, the paid amount and the arrival note');
ok(!str_contains(event_description(['forma' => 'online'] + $gb), 'zvoňte') && !str_contains($gd, 'cs_g1'), 'google: no arrival note online, no payment id in the text');
$om2 = mail_owner_new($gb, prague($gb['slot']), 'https://x/?b=1');
ok(str_contains($om2['ics'], 'UID:khgabc123@google.com') && str_contains($om2['ics'], 'METHOD:PUBLISH') && str_contains($om2['ics'], 'LOCATION:' . ics_text(PLACE)), 'google: her e-mail carries the event (same UID as the Google event)');
ok(str_contains($om2['text'], PLACE) && str_contains($om2['html'], h(PLACE)), 'google: her e-mail names the address in person');
$om3 = mail_owner_new(['forma' => 'online', 'meet' => 'https://meet.google.com/abc-defg-hij'] + $gb, prague($gb['slot']), 'https://x/?b=1');
ok(str_contains($om3['html'], 'background:#E8927C;border:1.5px solid #E8927C;color:#163A55">Připojit se přes Google Meet</a>') && str_contains($om3['text'], 'Google Meet: https://meet.google.com/abc-defg-hij') && str_contains($om3['ics'], 'LOCATION:https://meet.google.com/abc-defg-hij'), 'google: her e-mail has the Meet link online');
ok(mail_owner_new(['event_id' => null] + $gb, prague($gb['slot']), 'u')['ics'] === null, 'google: no event yet, no calendar file');
$sentEv = null;
$FAKE = ['#POST .*/events\?#' => function ($m, $u, $body) use (&$sentEv) { $sentEv = json_decode($body, true); return [200, ['id' => 'x']]; }];
gcal_create(prague($gb['slot']), 't', 'd', false, 'j@example.com', 'cs_g1');
ok(($sentEv['attendees'][0]['email'] ?? '') === 'j@example.com' && ($sentEv['extendedProperties']['private']['platba'] ?? '') === 'cs_g1' && !isset($sentEv['conferenceData']),
    'google: in person the client is a guest too, the payment kept out of sight');

unset($GLOBALS['HTTP_FAKE']);

echo "\n$n checks, $fails failed. Test e-mail: $tmp/test.eml\n";
exit($fails ? 1 : 0);
