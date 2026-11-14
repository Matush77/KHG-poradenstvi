<?php
/* Czech dates and the confirmation e-mail (plain text, HTML, .ics). Client-facing texts use "vy".
   Byte-safe without mbstring: UTF-8 is only split on character boundaries (preg /u). */
declare(strict_types=1);

require_once __DIR__ . '/smtp.php';

const PLACE = 'Vítkovická 3299/3a, 702 00 Ostrava';
const NAVIGATE = 'https://www.google.com/maps/dir/?api=1&destination=V%C3%ADtkovick%C3%A1+3299%2F3a%2C+702+00+Ostrava';
const PHONE = '+420 605 990 980';
const PHONE_HREF = 'tel:+420605990980';
const OWNER_MAIL = 'kristyna@khgporadenstvi.cz';
const FROM_MAIL = 'rezervace@khgporadenstvi.cz';
const SENDER = 'Kristýna Hriciková Glattová';

const CZ_DAYS = ['neděle', 'pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota'];            // by date('w')
const CZ_DAYS_UNTIL = ['neděle', 'pondělí', 'úterý', 'středy', 'čtvrtka', 'pátku', 'soboty'];       // after "do"
const CZ_DAYS_IN = ['v neděli', 'v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu'];
const CZ_MONTHS = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];

function ucfirst_utf8(string $s): string
{
    $first = preg_split('//u', $s, 2, PREG_SPLIT_NO_EMPTY)[0] ?? '';
    $up = ['ú' => 'Ú', 'č' => 'Č', 'š' => 'Š', 'ž' => 'Ž', 'ř' => 'Ř', 'ě' => 'Ě', 'á' => 'Á', 'í' => 'Í'][$first] ?? strtoupper($first);
    return $up . substr($s, strlen($first));
}

function hm(DateTimeImmutable $d): string { return $d->format('G:i'); }

/* 'v úterý 22. prosince v 13:00' */
function cz_at(DateTimeImmutable $d): string
{
    return CZ_DAYS_IN[(int)$d->format('w')] . ' ' . $d->format('j') . '. ' . CZ_MONTHS[(int)$d->format('n') - 1] . ' v ' . hm($d);
}

/* 'pondělí 21. prosince 1:00', read after "do" */
function cz_until(DateTimeImmutable $d): string
{
    return CZ_DAYS_UNTIL[(int)$d->format('w')] . ' ' . $d->format('j') . '. ' . CZ_MONTHS[(int)$d->format('n') - 1] . ' ' . hm($d);
}

/* 'Úterý 22. prosince 2026' */
function cz_day(DateTimeImmutable $d): string
{
    return ucfirst_utf8(CZ_DAYS[(int)$d->format('w')]) . ' ' . $d->format('j') . '. ' . CZ_MONTHS[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

/* Line item on Stripe's page: 'Sezení online · úterý 22. prosince · 13:00–13:50' */
function cz_label(string $forma, DateTimeImmutable $start): string
{
    $end = $start->modify('+50 minutes');
    return ($forma === 'osobne' ? 'Sezení osobně' : 'Sezení online') . ' · ' . CZ_DAYS[(int)$start->format('w')] . ' '
        . $start->format('j') . '. ' . CZ_MONTHS[(int)$start->format('n') - 1] . ' · ' . hm($start) . '–' . hm($end);
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

/* RFC 5545: lines over 75 bytes continue after CRLF + space, never inside a UTF-8 character. */
function ics_fold(string $line): string
{
    $out = [];
    $cur = '';
    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (strlen($cur) + strlen($ch) > ($out ? 74 : 75)) { $out[] = $cur; $cur = ''; }
        $cur .= $ch;
    }
    $out[] = $cur;
    return implode("\r\n ", $out);
}

function ics_text(string $s): string
{
    return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
}

/* The client's confirmation. $old: the previous start when the owner moved the session (subject, intro and
   the .ics change; the .ics keeps its UID with a higher SEQUENCE, so calendar apps update the same entry). */
function mail_build(array $b, DateTimeImmutable $start, string $cancelUrl, ?DateTimeImmutable $old = null, bool $byClient = false): array
{
    $online = $b['forma'] !== 'osobne';
    $end = $start->modify('+50 minutes');
    $deadline = $start->modify('-' . cfg()['free_cancel_hours'] . ' hours');
    $hours = hm($start) . '–' . hm($end);
    $when = cz_day($start) . ', ' . $hours;
    $kind = $online ? 'Online (Google Meet)' : 'Osobně';
    $meet = (string)($b['meet'] ?? '');
    $email = (string)($b['email'] ?? '');
    $subject = ($old ? 'Změna termínu: sezení ' : 'Potvrzení: sezení ') . cz_at($start);
    $intro = !$old ? 'vaše sezení je zaplacené a rezervované.'
        : ($byClient ? 'vaše sezení je přesunuté na nový termín.' : 'vaše sezení jsem přesunula na nový termín.');
    // the client may move (any number of times) or cancel for free until 36 hours before
    $rule = 'Termín můžete přesunout nebo zdarma zrušit do ' . cz_until($deadline) . ' (36 hodin předem), při zrušení vrátím celou částku. Později už se platba nevrací.';
    $linkLabel = 'Přesunout nebo zrušit termín';
    $oldTxt = $old ? 'Původní termín ' . cz_at($old) . ' už neplatí.' : '';

    if ($online) {
        $whereTxt = 'Odkaz na videohovor: ' . ($meet ?: '(doplní se)') . "\n"
            . "Když budete v Googlu přihlášení jako $email, pustí vás to rovnou. Jinak požádáte o vstup a já vás pustím.";
        $whereHtml = '<p style="margin:0 0 8px"><a href="' . h($meet ?: '#') . '" style="display:inline-block;background:#E8927C;color:#163A55;'
            . 'font-weight:600;text-decoration:none;padding:12px 20px;border-radius:999px">Připojit se k videohovoru</a></p>'
            . '<p style="margin:0;color:#44576A;font-size:14px">Když budete v Googlu přihlášení jako ' . h($email)
            . ', pustí vás to rovnou. Jinak požádáte o vstup a já vás pustím.</p>';
    } else {
        $whereTxt = 'Místo: ' . PLACE . "\nNavigace: " . NAVIGATE;
        $whereHtml = '<p style="margin:0">' . h(PLACE) . '<br><a href="' . h(NAVIGATE) . '" style="color:#2D5A7B">Navigovat</a></p>';
    }

    $text = implode("\n", [
        'Dobrý den,', '',
        $intro, '',
        $when, $kind . ' · 50 minut · 1 100 Kč', ...($old ? [$oldTxt] : []), '',
        $whereTxt, '',
        $rule,
        $linkLabel . ': ' . $cancelUrl, '',
        'Kdybyste cokoli potřebovali, napište mi na ' . OWNER_MAIL . ' nebo zavolejte na ' . PHONE . '.', '',
        'Těším se na vás', SENDER, 'khgporadenstvi.cz',
    ]);

    $tint = $online ? '#E8EFF4' : '#F6E6DC';
    $html = '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<meta name="color-scheme" content="light"><title>' . h($subject) . '</title></head>'
        . '<body style="margin:0;background:#FBF8F3;color:#163A55;font:16px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . '<p style="margin:0 0 16px">Dobrý den,</p>'
        . '<p style="margin:0 0 16px">' . $intro . '</p>'
        . '<div style="background:' . $tint . ';border-radius:16px;padding:16px 18px;margin:0 0 20px">'
        . '<b style="display:block;font-size:18px">' . h(cz_day($start)) . ', <span style="white-space:nowrap">' . $hours . '</span></b>'
        . '<span style="color:#44576A">' . $kind . ' · 50 minut · 1 100 Kč</span></div>'
        . ($old ? '<p style="margin:-8px 0 20px;color:#44576A;font-size:14px">' . h($oldTxt) . '</p>' : '')
        . $whereHtml
        . '<hr style="border:0;border-top:1px solid rgba(22,58,85,.14);margin:24px 0">'
        . '<p style="margin:0 0 8px">' . str_replace(h(cz_until($deadline)), '<b>' . h(cz_until($deadline)) . '</b>', h($rule)) . '</p>'
        . '<p style="margin:0 0 24px"><a href="' . h($cancelUrl) . '" style="color:#2D5A7B">' . $linkLabel . '</a></p>'
        . '<p style="margin:0 0 16px">Kdybyste cokoli potřebovali, napište mi na <a href="mailto:' . OWNER_MAIL . '" style="color:#2D5A7B">' . OWNER_MAIL . '</a> '
        . 'nebo zavolejte na <a href="' . PHONE_HREF . '" style="color:#2D5A7B">' . PHONE . '</a>.</p>'
        . '<p style="margin:0">Těším se na vás<br>' . SENDER . '</p>'
        . '<p style="margin:24px 0 0;color:#5E6E7C;font-size:13px">khgporadenstvi.cz · V příloze je termín do kalendáře.</p>'
        . '</div></body></html>';

    // METHOD:PUBLISH, not REQUEST: a plain "add to calendar" file, no invitation/RSVP flow in the client's mail app
    $f = fn(DateTimeImmutable $d) => $d->format('Ymd\THis');
    $desc = ($online && $meet ? "Připojit se: $meet\n" : '') . $linkLabel . ': ' . $cancelUrl;
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//khgporadenstvi.cz//rezervace//CS', 'METHOD:PUBLISH',
        'BEGIN:VTIMEZONE', 'TZID:Europe/Prague',
        'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000',
        'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
        'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000',
        'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD', 'END:VTIMEZONE',
        'BEGIN:VEVENT', 'UID:' . ($b['pay_id'] ?? $start->format('U')) . '@khgporadenstvi.cz',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'SEQUENCE:' . (int)($b['moves'] ?? 0),
        'DTSTART;TZID=Europe/Prague:' . $f($start), 'DTEND;TZID=Europe/Prague:' . $f($end),
        'SUMMARY:' . ics_text('Sezení s Kristýnou Hricikovou Glattovou'),
        'LOCATION:' . ics_text($online ? $meet : PLACE),
        'DESCRIPTION:' . ics_text($desc),
        'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Sezení za hodinu', 'TRIGGER:-PT1H', 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR',
    ];
    $ics = implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => $ics, 'to' => $email];
}

/* '10 h' under two days, else '3 dny' / '32 dní' */
function cz_ahead(float $hours): string
{
    if ($hours < 48) return max(0, (int)round($hours)) . ' h';
    $d = (int)round($hours / 24);
    return $d . ($d < 5 ? ' dny' : ' dní');
}

/* 'st 16. 12. v 14:00' for subjects */
function cz_short(DateTimeImmutable $d): string
{
    return ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'][(int)$d->format('w')] . ' ' . $d->format('j. n.') . ' v ' . hm($d);
}

/* Shared frame of the short e-mails: paragraphs of already escaped HTML, optional buttons [label => url]. */
function mail_frame(string $subject, array $paras, array $buttons = [], string $tint = '#E8EFF4', string $card = ''): string
{
    $btn = '';
    foreach ($buttons as $label => $url) {
        $btn .= '<a href="' . h($url) . '" style="display:inline-block;margin:0 8px 8px 0;background:' . ($btn === '' ? '#E8927C' : '#FFFFFF')
            . ';color:#163A55;font-weight:600;text-decoration:none;padding:12px 20px;border-radius:999px;border:1.5px solid '
            . ($btn === '' ? '#E8927C' : 'rgba(22,58,85,.25)') . '">' . h($label) . '</a>';
    }
    return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<meta name="color-scheme" content="light"><title>' . h($subject) . '</title></head>'
        . '<body style="margin:0;background:#FBF8F3;color:#163A55;font:16px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . ($card ? '<div style="background:' . $tint . ';border-radius:16px;padding:16px 18px;margin:0 0 20px">' . $card . '</div>' : '')
        . implode('', array_map(fn($p) => '<p style="margin:0 0 16px">' . $p . '</p>', $paras))
        . ($btn ? '<p style="margin:8px 0 16px">' . $btn . '</p>' : '')
        . '</div></body></html>';
}

/* The session line used in the owner's e-mails: [card html, plain text]. */
function owner_card(array $b, DateTimeImmutable $start): array
{
    $online = $b['forma'] !== 'osobne';
    $hours = hm($start) . '–' . hm($start->modify('+50 minutes'));
    $kind = $online ? 'Online (Google Meet)' : 'Osobně';
    $who = (string)($b['name'] ?: 'bez jména');
    $contact = array_filter([$b['email'] ?? null, $b['phone'] ?? null]);
    $html = '<b style="display:block;font-size:18px">' . h(cz_day($start)) . ', <span style="white-space:nowrap">' . $hours . '</span></b>'
        . '<span style="color:#44576A">' . $kind . ' · 50 minut · 1 100 Kč</span>'
        . '<span style="display:block;margin-top:10px"><b>' . h($who) . '</b>'
        . (!empty($b['email']) ? '<br><a href="mailto:' . h($b['email']) . '" style="color:#2D5A7B">' . h($b['email']) . '</a>' : '')
        . (!empty($b['phone']) ? '<br><a href="tel:' . h(preg_replace('/[^\d+]/', '', $b['phone'])) . '" style="color:#2D5A7B">' . h($b['phone']) . '</a>' : '')
        . '</span>';
    $text = cz_day($start) . ', ' . $hours . "\n" . $kind . " · 50 minut · 1 100 Kč\n" . $who . ($contact ? "\n" . implode("\n", $contact) : '');
    return [$html, $text];
}

/* To the owner, for every new booking: who, when, what they wrote, and the two buttons. */
function mail_owner_new(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    $subject = 'Nová rezervace: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start) . ' · ' . ($online ? 'online' : 'osobně');
    [$card, $cardTxt] = owner_card($b, $start);
    $note = trim((string)($b['note'] ?? ''));
    $paras = [];
    if ($note !== '') $paras[] = '<b>S čím přichází:</b><br>' . nl2br(h($note));
    if (!empty($b['clash'])) $paras[] = '<b style="color:#B5573F">Pozor:</b> v kalendáři je ve stejnou dobu i jiná událost.';
    if (!$online && empty($b['email'])) $paras[] = 'Klient nechal jen telefon, potvrzení e-mailem nedostal.';
    $paras[] = '<span style="color:#44576A;font-size:14px">Tlačítka rezervaci přesunou nebo zruší (klient dostane e-mail). '
        . 'Fungují každému, kdo má tento e-mail, proto ho nepřeposílat.</span>';
    $text = implode("\n", array_filter([
        'Nová rezervace', '', $cardTxt, '',
        $note !== '' ? "S čím přichází:\n$note\n" : null,
        !empty($b['clash']) ? "Pozor: v kalendáři je ve stejnou dobu i jiná událost.\n" : null,
        'Přesunout termín: ' . $ownerUrl . '&akce=presunout',
        'Zrušit a vrátit peníze: ' . $ownerUrl . '&akce=zrusit',
    ], fn($l) => $l !== null));
    $html = mail_frame($subject, $paras, ['Přesunout termín' => $ownerUrl . '&akce=presunout', 'Zrušit a vrátit peníze' => $ownerUrl . '&akce=zrusit'],
        $online ? '#E8EFF4' : '#F6E6DC', $card);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner when the client cancelled from their link. */
function mail_owner_cancelled(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    $subject = 'Zrušeno klientem: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start);
    [$card, $cardTxt] = owner_card($b, $start);
    $ahead = cz_ahead((float)$b['hours_before']);
    $money = $b['refund_id'] ? "Zrušeno $ahead předem, 1 100 Kč se klientovi vrací automaticky."
        : "Zrušeno jen $ahead předem, peníze se nevrátily.";
    $paras = [h($money), 'Událost z kalendáře je smazaná, termín je zase volný.'];
    // no refund button here (owner, 14 Nov): a late cancellation stays unpaid back
    $text = implode("\n", ['Klient zrušil rezervaci', '', $cardTxt, '', $money, 'Událost z kalendáře je smazaná, termín je zase volný.']);
    return ['subject' => $subject, 'text' => $text, 'html' => mail_frame($subject, $paras, [], $online ? '#E8EFF4' : '#F6E6DC', $card),
        'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner when the client moved the session from their link. */
function mail_owner_moved(array $b, DateTimeImmutable $start, DateTimeImmutable $old, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    $subject = 'Přesunuto klientem: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start);
    [$card, $cardTxt] = owner_card($b, $start);
    $was = 'Původně ' . cz_at($old) . '. Událost v kalendáři se posunula, původní čas je zase volný.';
    $count = (int)($b['client_moves'] ?? 1) > 1 ? 'Už je to ' . (int)$b['client_moves'] . '. přesun od klienta.' : '';
    $paras = array_values(array_filter([h($was), $count ? '<b>' . h($count) . '</b>' : '']));
    $text = implode("\n", array_filter(['Klient přesunul sezení', '', $cardTxt, '', $was, $count, '', 'Rezervace: ' . $ownerUrl], fn($l) => $l !== null));
    return ['subject' => $subject, 'text' => $text,
        'html' => mail_frame($subject, $paras, ['Otevřít rezervaci' => $ownerUrl], $online ? '#E8EFF4' : '#F6E6DC', $card),
        'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner when she deleted a session in Google Calendar: the booking is cancelled, the money not yet back. */
function mail_owner_deleted(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    $subject = 'Smazáno v kalendáři: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start);
    [$card, $cardTxt] = owner_card($b, $start);
    $what = 'Sezení jsi smazala v kalendáři, rezervace je proto zrušená a termín je zase volný.';
    $money = 'Peníze se zatím nevrátily a klient od webu nic nedostal. Tlačítko mu vrátí 1 100 Kč a pošle e-mail, že sezení rušíš.';
    $text = implode("\n", ['Smazané sezení', '', $cardTxt, '', $what, $money, '', 'Vrátit peníze a napsat klientovi: ' . $ownerUrl . '&akce=zrusit']);
    return ['subject' => $subject, 'text' => $text,
        'html' => mail_frame($subject, [h($what), h($money)], ['Vrátit peníze a napsat klientovi' => $ownerUrl . '&akce=zrusit'],
            $online ? '#E8EFF4' : '#F6E6DC', $card),
        'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner when an online client declined the calendar invitation. Nothing else changes. */
function mail_owner_declined(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $subject = 'Klient odmítl pozvánku: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start);
    [$card, $cardTxt] = owner_card($b, $start);
    $what = 'Klient v kalendáři odmítl pozvánku nebo si sezení smazal. Možná nepřijde, ale rezervace platí dál: termín zůstává obsazený a peníze se nevrátily.';
    $hint = 'Zrušit se dá jen jeho odkazem z potvrzení, nebo tlačítkem níže (vrátí mu celou částku). Můžeš mu taky odpovědět na tento e-mail.';
    $text = implode("\n", ['Klient odmítl pozvánku', '', $cardTxt, '', $what, $hint, '', 'Zrušit a vrátit peníze: ' . $ownerUrl . '&akce=zrusit']);
    return ['subject' => $subject, 'text' => $text,
        'html' => mail_frame($subject, [h($what), h($hint)], ['Zrušit a vrátit peníze' => $ownerUrl . '&akce=zrusit'], '#E8EFF4', $card),
        'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the client 30 minutes before: where to go (the Meet button or the address). No cancel link: too late for it. */
function mail_reminder(array $b, DateTimeImmutable $start): array
{
    $online = $b['forma'] !== 'osobne';
    $hours = hm($start) . '–' . hm($start->modify('+50 minutes'));
    $subject = 'Za půl hodiny: sezení dnes v ' . hm($start);
    $meet = (string)($b['meet'] ?? '');
    $email = (string)($b['email'] ?? '');
    $first = $online ? "za půl hodiny začíná naše online sezení ($hours)." : "za půl hodiny vás čekám na sezení ($hours).";
    $late = 'Kdybyste se zdrželi, napište mi nebo zavolejte na ' . PHONE . '.';
    if ($online) {
        $whereTxt = ['Připojit se: ' . ($meet ?: '(odkaz je v potvrzení)'),
            "Když budete v Googlu přihlášení jako $email, pustí vás to rovnou. Jinak požádáte o vstup a já vás pustím."];
        $whereHtml = '<a href="' . h($meet ?: '#') . '" style="display:inline-block;background:#E8927C;color:#163A55;font-weight:600;'
            . 'text-decoration:none;padding:12px 20px;border-radius:999px">Připojit se k videohovoru</a>'
            . '<br><span style="color:#44576A;font-size:14px">Když budete v Googlu přihlášení jako ' . h($email)
            . ', pustí vás to rovnou. Jinak požádáte o vstup a já vás pustím.</span>';
    } else {
        $whereTxt = [PLACE, 'Navigace: ' . NAVIGATE];
        $whereHtml = h(PLACE) . '<br><a href="' . h(NAVIGATE) . '" style="color:#2D5A7B">Navigovat</a>';
    }
    $text = implode("\n", ['Dobrý den,', '', $first, '', ...$whereTxt, '', $late, '', 'Těším se na vás', SENDER]);
    $card = '<b style="display:block;font-size:18px">Dnes, <span style="white-space:nowrap">' . $hours . '</span></b>'
        . '<span style="color:#44576A">' . ($online ? 'Online (Google Meet)' : 'Osobně') . ' · 50 minut</span>';
    $html = mail_frame($subject, ['Dobrý den,', h($first), $whereHtml, h($late)
        . ' <a href="' . PHONE_HREF . '" style="color:#2D5A7B">Zavolat</a>', 'Těším se na vás<br>' . SENDER], [], $online ? '#E8EFF4' : '#F6E6DC', $card);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => $email];
}

/* To the client when the owner cancels (full refund), or refunds a session the client cancelled late. */
function mail_client_cancelled(array $b, DateTimeImmutable $start, bool $refundOnly): array
{
    $book = site() . cfg()['booking_page'];
    $subject = $refundOnly ? 'Vracím vám platbu za sezení ' . cz_at($start) : 'Zrušené sezení ' . cz_at($start);
    $first = $refundOnly
        ? 'za zrušené sezení ' . cz_at($start) . ' vám přece jen vracím celou částku 1 100 Kč.'
        : 'sezení ' . cz_at($start) . ' musím bohužel zrušit. Omlouvám se. Celou částku 1 100 Kč vám vracím.';
    $money = 'Peníze přijdou na kartu, kterou jste platili, na účtu bývají do 5–10 pracovních dnů.';
    $text = implode("\n", ['Dobrý den,', '', $first, $money, '',
        ...($refundOnly ? [] : ['Pokud si chcete vybrat jiný termín, najdete je tady: ' . $book, '']),
        'Kdybyste cokoli potřebovali, napište mi na ' . OWNER_MAIL . ' nebo zavolejte na ' . PHONE . '.', '',
        'S pozdravem', SENDER, 'khgporadenstvi.cz']);
    $paras = ['Dobrý den,', h($first) . ' ' . h($money)];
    if (!$refundOnly) $paras[] = 'Pokud si chcete vybrat jiný termín, <a href="' . h($book) . '" style="color:#2D5A7B">najdete je v kalendáři</a>.';
    $paras[] = 'Kdybyste cokoli potřebovali, napište mi na <a href="mailto:' . OWNER_MAIL . '" style="color:#2D5A7B">' . OWNER_MAIL . '</a> '
        . 'nebo zavolejte na <a href="' . PHONE_HREF . '" style="color:#2D5A7B">' . PHONE . '</a>.';
    $paras[] = 'S pozdravem<br>' . SENDER;
    return ['subject' => $subject, 'text' => $text, 'html' => mail_frame($subject, $paras), 'ics' => null, 'to' => (string)$b['email']];
}

/* RFC 2047 for headers with Czech letters. */
function mime_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function mail_eml(array $m): string
{
    $b64 = fn(string $s) => rtrim(chunk_split(base64_encode($s), 76, "\r\n"));
    $outer = 'mix-' . bin2hex(random_bytes(8));
    $alt = 'alt-' . bin2hex(random_bytes(8));
    $domain = substr(FROM_MAIL, strpos(FROM_MAIL, '@') + 1);
    return implode("\r\n", [
        'From: ' . mime_header(SENDER) . ' <' . FROM_MAIL . '>',
        'To: <' . $m['to'] . '>',
        'Reply-To: <' . ($m['reply_to'] ?? OWNER_MAIL) . '>',
        'Subject: ' . mime_header($m['subject']),
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $outer . '"',
        '',
        '--' . $outer,
        'Content-Type: multipart/alternative; boundary="' . $alt . '"',
        '',
        '--' . $alt,
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: base64',
        '', $b64($m['text']),
        '--' . $alt,
        'Content-Type: text/html; charset=utf-8',
        'Content-Transfer-Encoding: base64',
        '', $b64($m['html']),
        '--' . $alt . '--',
        '',
        ...(empty($m['ics']) ? [] : [
            '--' . $outer,
            'Content-Type: text/calendar; charset=utf-8; method=PUBLISH; name="sezeni.ics"',
            'Content-Disposition: attachment; filename="sezeni.ics"',
            'Content-Transfer-Encoding: base64',
            '', $b64($m['ics'])]),
        '--' . $outer . '--',
        '',
    ]);
}

/* Live: SMTP through the send-only address. Lab: always a copy in the outbox folder (the previews), and
   through SMTP only to the allowed test addresses, subject marked [TEST]; the owner's e-mails go to
   LAB_OWNER_TO instead of her inbox. Without SMTP settings the lab just keeps the copy. */
function mail_send(string $name, array $m): void
{
    $c = cfg();
    if ($m['to'] === '') throw new RuntimeException('No recipient.');
    if (lab()) {
        $dir = $c['outbox'];
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $path = "$dir/" . preg_replace('/[^A-Za-z0-9_-]/', '', $name) . '.eml';
        file_put_contents($path, mail_eml($m), LOCK_EX);
        @chmod($path, 0600);
    }
    if ($c['smtp']['pass'] === '') {
        if (lab()) return;
        throw new RuntimeException('SMTP is not set up (SMTP_PASS missing).');
    }
    if (lab()) {
        $to = strtolower($m['to'] === OWNER_MAIL ? $c['lab_owner_to'] : $m['to']);
        if ($to === '' || !in_array($to, $c['lab_mail_to'], true)) return;
        $m['to'] = $to;
        $m['subject'] = '[TEST] ' . $m['subject'];
    }
    smtp_send($c['smtp'], FROM_MAIL, $m['to'], mail_eml($m));
    log_line('SMTP_SENT', ['mail' => $name]);
}
