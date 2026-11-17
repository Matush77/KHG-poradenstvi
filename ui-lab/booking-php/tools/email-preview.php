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
        ['moved', 'Moved by Kristýna', 'She moved it; says who moved it', mail_build($b + ['moves' => 1], $start, $cancel, $old)],
        ['moved-self', 'Moved by the client', 'The client moved it from their link', mail_build($b + ['moves' => 1], $start, $cancel, $old, true)],
        ['day', 'Reminder, day before', '24 hours before', mail_reminder($b, $start, 'day', $dayBefore)],
        ['soon', 'Reminder, 10 minutes', '10 minutes before', mail_reminder($b, $start, 'soon', $sameDay)],
        ['cancelled', 'Cancelled by Kristýna', 'She cancels; full refund', mail_client_cancelled($b, $start, false, true, true)],
        ['self', 'Cancelled by the client', 'Client cancels 36+ hours before; full refund', mail_client_cancelled($b, $start, false)],
        ['self-late', 'Cancelled late by the client', 'Client cancels under 36 hours before; no refund', mail_client_cancelled($b, $start, false, false)],
        ['refund', 'Refund of a late cancel', 'She refunds a session the client cancelled late', mail_client_cancelled($b, $start, true)],
        ['cancelled-delayed', 'Cancelled by Kristýna, refund delayed', 'She cancels, Stripe refuses the refund; she is told separately',
            mail_client_cancelled($b, $start, false, true, true, true)],
        ['self-delayed', 'Cancelled by the client, refund delayed', 'Client cancels 36+ hours before, Stripe refuses the refund',
            mail_client_cancelled($b, $start, false, true, false, true)],
        ['double', 'Slot taken meanwhile', 'Paid for a time someone else had just booked; refunded', mail_client_double($b, $start)],
        ['missed', 'Booking not completed', 'Payment processed only after the session start (outage); refunded',
            mail_client_double($b + ['cancelled_by' => 'missed'], $start)],
    ];
    $add('client', $forma, $list);
    // Kristýna's e-mails (to her own address; replying goes to the client)
    $own = owner_url('cs_preview');
    // her new-booking e-mail carries the calendar file once the Google event exists (it always does by then)
    $note = $b + ['event_id' => 'preview', 'note' => "Poslední měsíce špatně spím a v práci mě přepadá úzkost.\nChtěla bych to zkusit řešit."];
    $add('owner', $forma, [
        ['new', 'New booking', 'Right after the client pays', mail_owner_new($note, $start, $own)],
        ['new-clash', 'New booking, clash', 'She had something else in the calendar meanwhile', mail_owner_new($b + ['clash' => 1, 'event_id' => 'preview'], $start, $own)],
        ['cancelled', 'Cancelled by the client', 'Client cancels 36+ hours before; refunded automatically',
            mail_owner_cancelled($b + ['hours_before' => 50.0, 'refund_id' => 're_preview'], $start, $own)],
        ['cancelled-late', 'Cancelled late by the client', 'Client cancels under 36 hours before; no refund',
            mail_owner_cancelled($b + ['hours_before' => 20.0, 'refund_id' => null], $start, $own)],
        ['moved', 'Moved by the client', 'Client moved it from their link', mail_owner_moved($b + ['client_moves' => 1], $start, $old, $own)],
        ['moved-2', 'Moved again by the client', 'From the second move: with the count', mail_owner_moved($b + ['client_moves' => 2], $start, $old, $own)],
        ['deleted', 'Deleted in the calendar', 'She deleted the event in Google Calendar', mail_owner_deleted($b, $start, $own)],
        ['declined', 'Invitation declined', 'An online client declined the calendar invitation', mail_owner_declined($b, $start, $own)],
        ...($forma === 'osobne' ? [['new-phone', 'New booking, phone only', 'In person, the client left no e-mail',
            mail_owner_new(['email' => '', 'event_id' => 'preview'] + $b, $start, $own)]] : []),
        ['refund-failed', 'Refund failed', 'Stripe refused a refund (at once or later)',
            mail_owner_refund_failed($b + ['refund_status' => 'failed'], $start, $own)],
        ['refund-partial', 'Refund only partly back', 'Stripe returned only part of the money',
            mail_owner_refund_failed($b + ['refund_status' => 'partial'], $start, $own)],
        ['refund-missing', 'Refund missing in Stripe', 'The refund vanished from Stripe',
            mail_owner_refund_failed($b + ['refund_status' => 'missing'], $start, $own)],
        ['refund-action', 'Refund waiting in Stripe', 'Stripe needs more details before it sends the money',
            mail_owner_refund_failed($b + ['refund_status' => 'requires_action'], $start, $own)],
        ['refund-found', 'Refunded in Stripe, session stands', 'She refunded in Stripe directly; the booking is still on',
            mail_owner_refund_found($b + ['status' => 'booked'], $start, $own)],
        ['refund-found-unbooked', 'Payment already refunded', 'A payment reached the site after it was refunded; no booking made',
            mail_owner_refund_found($b + ['status' => 'cancelled'], $start, $own)],
        ['double', 'Two payments, one slot', 'A second payment for a taken time; refunded', mail_owner_double($b, $start, true)],
        ['double-failed', 'Two payments, refund failed', 'A second payment for a taken time; refund did not go', mail_owner_double($b, $start, false)],
        ['missed', 'Payment after the session', 'Processed only after the session start (outage); refunded',
            mail_owner_double($b + ['cancelled_by' => 'missed'], $start, true)],
        ['missed-failed', 'Payment after the session, refund failed', 'As above, but the refund did not go',
            mail_owner_double($b + ['cancelled_by' => 'missed'], $start, false)],
        ['calendar-failed', 'Not written to the calendar', 'Paid, but Google refused the event for half an hour',
            mail_owner_calendar_failed($b, $start)],
        ['undeliverable', 'E-mail to the client failed', 'A client e-mail bounced or kept failing for days',
            mail_owner_undeliverable($b, $start, $own, 'confirmation')],
    ]);
}

$page = (string)file_get_contents(__DIR__ . '/email-preview.tpl.html');
$json = json_encode(['mails' => $mails, 'from' => SENDER . ' <' . FROM_MAIL . '>'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
file_put_contents(__DIR__ . '/../email-preview.html', str_replace('/*DATA*/null', $json, $page));
array_map('unlink', glob("$tmp/*"));
rmdir($tmp);
echo "written: ui-lab/booking-php/email-preview.html\n";
