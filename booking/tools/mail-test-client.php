<?php
/* Lab: sends every e-mail the client gets (the six in email-preview.html) through the real SMTP to LAB_MAIL_TO
   addresses, subjects marked [TEST]. Sample booking: online (or osobne with --osobne), in 10 days at 14:00.
   php booking/tools/mail-test-client.php address [address ...] [--osobne] [--only=potvrzeni,zruseni,pozde] */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/mail.php';
require __DIR__ . '/../src/booking.php';

if (!lab()) exit("Lab only.\n");
$c = cfg();
if ($c['smtp']['pass'] === '') exit("SMTP_PASS is empty in {$c['dir']}/smtp.env\n");
$args = array_slice($argv, 1);
$forma = in_array('--osobne', $args, true) ? 'osobne' : 'online';
$only = [];
foreach ($args as $a) if (str_starts_with($a, '--only=')) $only = explode(',', substr($a, 7));
$to = array_map('strtolower', array_values(array_filter($args, fn($a) => $a[0] !== '-')));
if (!$to) exit("Give at least one address from LAB_MAIL_TO.\n");
foreach ($to as $t) if (!in_array($t, $c['lab_mail_to'], true)) exit("$t is not in LAB_MAIL_TO.\n");

$start = slot_start((new DateTimeImmutable('+10 days'))->format('Y-m-d'), '14:00');
$old = $start->modify('-1 day');
foreach ($to as $t) {
    $b = ['pay_id' => 'cs_test_mailtest', 'forma' => $forma, 'name' => 'Test Klient', 'email' => $t, 'phone' => null,
        'meet' => 'https://meet.google.com/abc-defg-hij', 'moves' => 0];
    $mails = [
        'potvrzeni' => mail_build($b, $start, cancel_url('cs_test_mailtest')),
        'zmena' => mail_build($b + ['moves' => 1], $start, cancel_url('cs_test_mailtest'), $old),
        'pripominka' => mail_reminder($b, $start, 'day', $start->modify('-24 hours')->getTimestamp()),
        'za10' => mail_reminder($b, $start, 'soon', $start->modify('-10 minutes')->getTimestamp()),
        'zruseni' => mail_client_cancelled($b, $start, false),
        'vraceni' => mail_client_cancelled($b, $start, true),
        'pozde' => mail_client_cancelled($b, $start, false, false),
    ];
    foreach ($mails as $name => $m) {
        if ($only && !in_array($name, $only, true)) continue;
        mail_send("mailtest-$name", $m);
        echo "sent: $name -> $t\n";
    }
}
