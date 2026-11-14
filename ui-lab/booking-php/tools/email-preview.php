<?php
/* Lab: builds email-preview.html, every e-mail the client and Kristýna get, rendered by the real mail.php code,
   with tabs for the client / Kristýna, phone / tablet / desktop and online / in person. Nothing is sent.
   php ui-lab/booking-php/tools/email-preview.php */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
$tmp = sys_get_temp_dir() . '/khg-mail-preview-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700);
file_put_contents("$tmp/stripe-test.env", "STRIPE_SECRET_KEY=sk_test_dummy\n");
file_put_contents("$tmp/booking-secret", str_repeat('ab', 32));
file_put_contents("$tmp/booking.env", "SITE_URL=https://khgporadenstvi.cz\nCANCEL_PAGE=/rezervace/zruseni.html\nBOOKING_PAGE=/rezervace.html\nOWNER_PAGE=/rezervace/sprava.html\n");
putenv("KHG_CONFIG_DIR=$tmp");

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/mail.php';
require __DIR__ . '/../src/booking.php';

$start = slot_start('2026-11-24', '14:00');                 // a Tuesday
$old = slot_start('2026-11-23', '9:00');
$dayBefore = slot_start('2026-11-23', '14:00')->getTimestamp();
$sameDay = slot_start('2026-11-24', '13:50')->getTimestamp();
$brand = 'data:image/png;base64,' . base64_encode(brand_png());

$mails = ['client' => [], 'owner' => []];
$add = function (string $who, string $forma, array $list) use (&$mails, $brand) {
    foreach ($list as [$id, $name, $when, $m]) {
        $mails[$who][$forma][] = ['id' => $id, 'name' => $name, 'when' => $when, 'subject' => $m['subject'], 'text' => $m['text'],
            'to' => $m['to'] ?? null, 'ics' => !empty($m['ics']), 'html' => str_replace(
                // the brand travels inside the e-mail; the fonts come from this checkout (the live site has no Fraunces yet)
                ['cid:' . BRAND_CID, MAIL_FONTS . 'Inter/', MAIL_FONTS . 'Fraunces/'],
                [$brand, '/fonts/Inter/', '/ui-lab/proposal/fonts/'], $m['html'])];
    }
};
foreach (['online', 'osobne'] as $forma) {
    $b = ['pay_id' => 'cs_preview', 'forma' => $forma, 'name' => 'Jana Nováková', 'email' => 'jana.novakova@example.com',
        'phone' => '+420 777 123 456', 'meet' => 'https://meet.google.com/abc-defg-hij', 'moves' => 0];
    $cancel = cancel_url('cs_preview');
    $list = [
        ['confirm', 'Booking confirmed', 'Right after payment', mail_build($b, $start, $cancel)],
        ['moved', 'Session moved', 'After a move (by the client or by Kristýna)', mail_build($b + ['moves' => 1], $start, $cancel, $old)],
        ['day', 'Reminder, day before', '24 hours before', mail_reminder($b, $start, 'day', $dayBefore)],
        ['soon', 'Reminder, 10 minutes', '10 minutes before', mail_reminder($b, $start, 'soon', $sameDay)],
        ['cancelled', 'Cancelled by Kristýna', 'She cancels; full refund', mail_client_cancelled($b, $start, false)],
        ['self', 'Cancelled by the client', 'Client cancels 36+ hours before; full refund', mail_client_cancelled($b, $start, false)],
        ['self-late', 'Cancelled late by the client', 'Client cancels under 36 hours before; no refund', mail_client_cancelled($b, $start, false, false)],
        ['refund', 'Refund of a late cancel', 'She refunds a session the client cancelled late', mail_client_cancelled($b, $start, true)],
    ];
    $add('client', $forma, $list);
    // Kristýna's e-mails (to her own address; replying goes to the client)
    $own = owner_url('cs_preview');
    $note = $b + ['note' => "Poslední měsíce špatně spím a v práci mě přepadá úzkost.\nChtěla bych to zkusit řešit."];
    $add('owner', $forma, [
        ['new', 'New booking', 'Right after the client pays', mail_owner_new($note, $start, $own)],
        ['new-clash', 'New booking, clash', 'She had something else in the calendar meanwhile', mail_owner_new($b + ['clash' => 1], $start, $own)],
        ['cancelled', 'Cancelled by the client', 'Client cancels 36+ hours before; refunded automatically',
            mail_owner_cancelled($b + ['hours_before' => 50.0, 'refund_id' => 're_preview'], $start, $own)],
        ['cancelled-late', 'Cancelled late by the client', 'Client cancels under 36 hours before; no refund',
            mail_owner_cancelled($b + ['hours_before' => 20.0, 'refund_id' => null], $start, $own)],
        ['moved', 'Moved by the client', 'Client moved it from their link', mail_owner_moved($b + ['client_moves' => 1], $start, $old, $own)],
        ['moved-2', 'Moved again by the client', 'From the second move: with the count', mail_owner_moved($b + ['client_moves' => 2], $start, $old, $own)],
        ['deleted', 'Deleted in the calendar', 'She deleted the event in Google Calendar', mail_owner_deleted($b, $start, $own)],
        ['declined', 'Invitation declined', 'An online client declined the calendar invitation', mail_owner_declined($b, $start, $own)],
    ]);
}

$page = (string)file_get_contents(__DIR__ . '/email-preview.tpl.html');
$json = json_encode(['mails' => $mails, 'from' => SENDER . ' <' . FROM_MAIL . '>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
file_put_contents(__DIR__ . '/../email-preview.html', str_replace('/*DATA*/null', $json, $page));
array_map('unlink', glob("$tmp/*"));
rmdir($tmp);
echo "written: ui-lab/booking-php/email-preview.html\n";
