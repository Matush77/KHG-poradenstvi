<?php
/* Lab: sends a sample confirmation and a sample "Nová rezervace" through the real SMTP, to one
   LAB_MAIL_TO address (the first, or the one given; subjects marked [TEST]).
   php booking/tools/mail-test.php [address] */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/store.php';
require __DIR__ . '/../src/mail.php';
require __DIR__ . '/../src/booking.php';

if (!lab()) exit("Lab only.\n");
$c = cfg();
if ($c['smtp']['pass'] === '') exit("SMTP_PASS is empty in {$c['dir']}/smtp.env\n");
if (!$c['lab_mail_to']) exit("LAB_MAIL_TO is empty in {$c['dir']}/smtp.env\n");
$to = strtolower($argv[1] ?? $c['lab_mail_to'][0]);
if (!in_array($to, $c['lab_mail_to'], true)) exit("$to is not in LAB_MAIL_TO.\n");
$start = slot_start((new DateTimeImmutable('+10 days'))->format('Y-m-d'), '14:00');
$b = ['pay_id' => 'cs_test_mailtest', 'forma' => 'online', 'name' => 'Test Klient', 'email' => $to, 'phone' => null,
    'meet' => 'https://meet.google.com/abc-defg-hij', 'note' => 'Zkušební zpráva, žádná skutečná rezervace.', 'clash' => 0, 'moves' => 0];
mail_send('mailtest-client', mail_build($b, $start, cancel_url('cs_test_mailtest')));
echo "sent: confirmation -> $to\n";
mail_send('mailtest-owner', mail_owner_new($b, $start, owner_url('cs_test_mailtest')));
echo "sent: owner e-mail -> {$c['lab_owner_to']}\n";
