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

/* ---- The look of every e-mail: the redesign's theme (paper ground, navy text, a sky panel for online and a
   peach one for in person, coral pill buttons, Fraunces headings, the heart-and-name brand of the new header).
   E-mail apps don't load web fonts, so the brand is a small PNG drawn from the site's own fonts
   (assets/brand.png), sent inside the e-mail (cid), so it shows without the live site; headings fall back to
   Georgia, body text to the system font. No greetings or sign-offs; the footer carries her name and contacts. */
const BRAND_CID = 'brand@khgporadenstvi.cz';
const MAIL_FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
const MAIL_SERIF = "Fraunces,Georgia,'Times New Roman',serif";

function brand_png(): string { return (string)file_get_contents(__DIR__ . '/../assets/brand.png'); }

function mail_tint(array $b): string { return ($b['forma'] ?? '') === 'osobne' ? '#F6E6DC' : '#E8EFF4'; }

function mail_btn(string $label, string $url, bool $primary = true): string
{
    return '<a href="' . h($url) . '" style="display:inline-block;margin:0 8px 8px 0;padding:12px 22px;border-radius:999px;'
        . 'font-weight:600;font-size:16px;text-decoration:none;color:#163A55;'
        . ($primary ? 'background:#E8927C;border:1.5px solid #E8927C' : 'background:#FFFFFF;border:1.5px solid #D9D4CC') . '">' . h($label) . '</a>';
}

function mail_muted(string $html): string { return '<span style="color:#5E6E7C;font-size:14px">' . $html . '</span>'; }

function mail_link(string $label, string $url): string { return '<a href="' . h($url) . '" style="color:#2D5A7B">' . h($label) . '</a>'; }

/* $panel: html inside the tinted panel; $blocks: html paragraphs under it. */
function mail_page(string $subject, string $title, string $panel, array $blocks, string $tint, string $footNote = '', bool $foot = true): string
{
    $p = fn(string $x) => '<p style="margin:16px 0 0;color:#44576A">' . $x . '</p>';
    return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . h($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#FBF8F3;color:#163A55;font:16px/1.55 ' . MAIL_FONT . '">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px 32px">'
        . '<a href="https://khgporadenstvi.cz" style="display:inline-block;text-decoration:none"><img src="cid:' . BRAND_CID . '" width="255" height="35" '
        . 'alt="Kristýna Hriciková Glattová · psycholog a terapeut" style="display:block;border:0;width:255px;height:35px;color:#163A55;font:500 17px ' . MAIL_SERIF . '"></a>'
        . '<h1 style="margin:28px 0 16px;font:500 28px/1.2 ' . MAIL_SERIF . ';color:#163A55;letter-spacing:-.01em">' . h($title) . '</h1>'
        . '<div style="background:' . $tint . ';border-radius:16px;padding:20px">' . $panel . '</div>'
        . implode('', array_map($p, array_filter($blocks)))
        . (!$foot ? '' : '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #E3E0DA;color:#5E6E7C;font-size:13px;line-height:1.6">'
        . SENDER . '<br><a href="' . PHONE_HREF . '" style="color:#5E6E7C">' . PHONE . '</a> · '
        . '<a href="mailto:' . OWNER_MAIL . '" style="color:#5E6E7C">' . OWNER_MAIL . '</a> · '
        . '<a href="https://khgporadenstvi.cz" style="color:#5E6E7C">khgporadenstvi.cz</a>'
        . ($footNote ? '<br>' . h($footNote) : '') . '</div>')
        . '</div></body></html>';
}

/* The session inside the panel: when, what, and (when $action) where to go: the Meet button or the address. */
function session_panel(array $b, DateTimeImmutable $start, bool $action, ?string $dayTitle = null): string
{
    $online = $b['forma'] !== 'osobne';
    $hours = hm($start) . '–' . hm($start->modify('+50 minutes'));
    $html = '<div style="font:500 20px/1.3 ' . MAIL_SERIF . ';color:#163A55">' . h($dayTitle ?? cz_day($start)) . ', <span style="white-space:nowrap">' . $hours . '</span></div>'
        . '<div style="color:#44576A;font-size:15px">' . ($online ? 'Online · Google Meet' : 'Osobně') . ' · 50 minut · 1 100 Kč</div>';
    if (!$action) return $html;
    $meet = (string)($b['meet'] ?? '');
    return $html . ($online
        ? '<div style="margin-top:16px">' . mail_btn('Připojit se k videohovoru', $meet ?: '#') . '</div>'
        : '<div style="margin-top:12px;color:#163A55">' . h(PLACE) . '<br>' . mail_link('Navigovat', NAVIGATE) . '</div>');
}

/* Plain-text twin of the panel. */
function session_text(array $b, DateTimeImmutable $start, bool $action): array
{
    $online = $b['forma'] !== 'osobne';
    $lines = [cz_day($start) . ', ' . hm($start) . '–' . hm($start->modify('+50 minutes')),
        ($online ? 'Online · Google Meet' : 'Osobně') . ' · 50 minut · 1 100 Kč'];
    if ($action) $lines[] = $online ? 'Připojit se: ' . (($b['meet'] ?? '') ?: '(doplní se)') : PLACE . "\nNavigace: " . NAVIGATE;
    return $lines;
}

function text_foot(): array { return ['', '—', SENDER . ' · ' . PHONE . ' · ' . OWNER_MAIL . ' · khgporadenstvi.cz']; }

/* The client's confirmation, and "Termín je změněný" after a move ($old: the previous start). Plain text, HTML, .ics. */
function mail_build(array $b, DateTimeImmutable $start, string $cancelUrl, ?DateTimeImmutable $old = null, bool $byClient = false): array
{
    $online = $b['forma'] !== 'osobne';
    $end = $start->modify('+50 minutes');
    $deadline = $start->modify('-' . cfg()['free_cancel_hours'] . ' hours');
    $meet = (string)($b['meet'] ?? '');
    $email = (string)($b['email'] ?? '');
    $subject = ($old ? 'Změna termínu: sezení ' : 'Potvrzení: sezení ') . cz_at($start);
    $title = $old ? 'Termín je změněný' : 'Sezení je rezervované';
    $oldTxt = $old ? 'Původní termín ' . cz_at($old) . ' už neplatí.' : '';
    $login = $online && $email ? "Když budete v Googlu přihlášení jako $email, pustí vás to rovnou. Jinak požádáte o vstup." : '';
    $rule = 'Přesunout nebo zdarma zrušit můžete do ' . cz_until($deadline) . '. Později se platba nevrací.';
    $linkLabel = 'Přesunout nebo zrušit termín';

    $text = implode("\n", [$title, '', ...session_text($b, $start, true), ...($old ? [$oldTxt] : []), ...($login ? [$login] : []), '',
        $rule, $linkLabel . ': ' . $cancelUrl, ...text_foot()]);
    $html = mail_page($subject, $title, session_panel($b, $start, true), [
        $old ? h($oldTxt) : '',
        $login ? mail_muted(h($login)) : '',
        'Přesunout nebo zdarma zrušit můžete do <b style="color:#163A55">' . h(cz_until($deadline)) . '</b>. Později se platba nevrací.<br>' . mail_link($linkLabel, $cancelUrl),
    ], mail_tint($b), 'V příloze je termín do kalendáře.');

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

/* The owner's panel: the session and the client with contacts. [html, text lines] */
function owner_panel(array $b, DateTimeImmutable $start): array
{
    $who = (string)($b['name'] ?: 'bez jména');
    $html = session_panel($b, $start, false)
        . '<div style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(22,58,85,.12)"><b style="color:#163A55">' . h($who) . '</b>'
        . (!empty($b['email']) ? '<br>' . mail_link($b['email'], 'mailto:' . $b['email']) : '')
        . (!empty($b['phone']) ? '<br>' . mail_link($b['phone'], 'tel:' . preg_replace('/[^\d+]/', '', $b['phone'])) : '') . '</div>';
    return [$html, [...session_text($b, $start, false), $who, ...array_filter([$b['email'] ?? null, $b['phone'] ?? null])]];
}

/* Owner e-mails share this: title, panel with the client, paragraphs, buttons [label => url]. */
function owner_mail(array $b, DateTimeImmutable $start, string $subject, string $title, array $paras, array $buttons = [], array $textExtra = []): array
{
    [$panel, $panelTxt] = owner_panel($b, $start);
    $btns = '';
    foreach ($buttons as $label => $url) $btns .= mail_btn($label, $url, $btns === '');
    $html = mail_page($subject, $title, $panel, [...array_map('h', $paras), $btns], mail_tint($b), '', false);   // her own e-mails: no footer
    $text = implode("\n", [$title, '', ...$panelTxt, '', ...$paras, ...$textExtra,
        ...array_map(fn($l, $u) => "$l: $u", array_keys($buttons), $buttons)]);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner, for every new booking: who, when, what they wrote, and the two buttons. */
function mail_owner_new(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    $subject = 'Nová rezervace: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start) . ' · ' . ($online ? 'online' : 'osobně');
    $note = trim((string)($b['note'] ?? ''));
    $buttons = ['Přesunout termín' => $ownerUrl . '&akce=presunout', 'Zrušit a vrátit peníze' => $ownerUrl . '&akce=zrusit'];
    [$panel, $panelTxt] = owner_panel($b, $start);
    $btns = mail_btn('Přesunout termín', $buttons['Přesunout termín']) . mail_btn('Zrušit a vrátit peníze', $buttons['Zrušit a vrátit peníze'], false);
    $html = mail_page($subject, 'Nová rezervace', $panel, [
        $note !== '' ? '<b style="color:#163A55">S čím přichází</b><br><span style="color:#163A55">' . nl2br(h($note)) . '</span>' : '',
        !empty($b['clash']) ? '<b style="color:#B5573F">V kalendáři je ve stejnou dobu i jiná událost.</b>' : '',
        !$online && empty($b['email']) ? 'Klient nechal jen telefon, potvrzení e-mailem nedostal.' : '',
        $btns,
        mail_muted('Odkazy fungují bez přihlášení, e-mail proto nepřeposílej.'),
    ], mail_tint($b), '', false);
    $text = implode("\n", array_filter(['Nová rezervace', '', ...$panelTxt, '',
        $note !== '' ? "S čím přichází:\n$note\n" : null,
        !empty($b['clash']) ? "V kalendáři je ve stejnou dobu i jiná událost.\n" : null,
        'Přesunout termín: ' . $buttons['Přesunout termín'],
        'Zrušit a vrátit peníze: ' . $buttons['Zrušit a vrátit peníze'],
    ], fn($l) => $l !== null));
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* To the owner when the client cancelled from their link. No refund button (owner, 14 Nov). */
function mail_owner_cancelled(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $ahead = cz_ahead((float)$b['hours_before']);
    $money = $b['refund_id'] ? "Zrušeno $ahead předem, 1 100 Kč se klientovi vrací automaticky."
        : "Zrušeno jen $ahead předem, peníze se nevrátily.";
    return owner_mail($b, $start, 'Zrušeno klientem: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Zrušeno klientem',
        [$money, 'Událost z kalendáře je smazaná, termín je zase volný.']);
}

/* To the owner when the client moved the session from their link; from the second move with the count. */
function mail_owner_moved(array $b, DateTimeImmutable $start, DateTimeImmutable $old, string $ownerUrl): array
{
    $n = (int)($b['client_moves'] ?? 1);
    return owner_mail($b, $start, 'Přesunuto klientem: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Přesunuto klientem',
        array_values(array_filter(['Původně ' . cz_at($old) . '. Původní čas je zase volný.', $n > 1 ? "Už je to $n. přesun od klienta." : ''])),
        ['Otevřít rezervaci' => $ownerUrl]);
}

/* To the owner when she deleted a session in Google Calendar: cancelled, the money not yet back. */
function mail_owner_deleted(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    return owner_mail($b, $start, 'Smazáno v kalendáři: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Smazáno v kalendáři',
        ['Rezervace je zrušená a termín volný. Peníze se zatím nevrátily a klient od webu nic nedostal.',
         'Tlačítko vrátí 1 100 Kč a pošle klientovi e-mail, že sezení rušíš.'],
        ['Vrátit peníze a napsat klientovi' => $ownerUrl . '&akce=zrusit']);
}

/* To the owner when an online client declined the calendar invitation. Nothing else changes. */
function mail_owner_declined(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    return owner_mail($b, $start, 'Klient odmítl pozvánku: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Klient odmítl pozvánku',
        ['Klient v kalendáři odmítl pozvánku nebo si sezení smazal. Rezervace platí dál: termín zůstává obsazený, peníze se nevrátily.'],
        ['Zrušit a vrátit peníze' => $ownerUrl . '&akce=zrusit']);
}

/* The reminders (like Google's booking page sends today): 'day' 24 hours before, 'soon' 10 minutes before. */
function mail_reminder(array $b, DateTimeImmutable $start, string $kind, ?int $now = null): array
{
    $today = (new DateTimeImmutable('@' . ($now ?? time())))->setTimezone(new DateTimeZone('Europe/Prague'));
    $diff = (int)$today->setTime(0, 0)->diff($start->setTime(0, 0))->format('%r%a');
    $dayWord = $diff === 0 ? 'dnes' : ($diff === 1 ? 'zítra' : null);
    if ($kind === 'soon') {
        $subject = 'Za 10 minut: sezení v ' . hm($start);
        $title = 'Za 10 minut začínáme';
    } else {
        $subject = 'Připomínka: sezení ' . ($dayWord ? $dayWord . ' v ' . hm($start) : cz_at($start));
        $title = 'Připomínka';                                       // the panel right under it says when
    }
    $panelDay = $dayWord ? ucfirst_utf8($dayWord) : null;
    $url = cancel_url($b['pay_id']);
    $html = mail_page($subject, $title, session_panel($b, $start, true, $panelDay),
        [$kind === 'day' ? mail_link('Zrušit termín', $url) : ''], mail_tint($b));
    $text = implode("\n", [$title, '', ...session_text($b, $start, true), ...($kind === 'day' ? ['', 'Zrušit termín: ' . $url] : []), ...text_foot()]);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => (string)($b['email'] ?? '')];
}

/* To the client when the owner cancels (full refund), or refunds a session the client cancelled late. */
function mail_client_cancelled(array $b, DateTimeImmutable $start, bool $refundOnly): array
{
    $book = site() . cfg()['booking_page'];
    $subject = $refundOnly ? 'Vrácení platby za sezení ' . cz_at($start) : 'Zrušené sezení ' . cz_at($start);
    $title = $refundOnly ? 'Vracím vám platbu' : 'Sezení je zrušené';
    $first = $refundOnly ? 'Za zrušené sezení vám vracím celých 1 100 Kč.' : 'Sezení musím zrušit, omlouvám se. Celých 1 100 Kč vám vracím.';
    $money = 'Peníze přijdou na kartu, kterou jste platili, obvykle do 5–10 pracovních dnů.';
    $text = implode("\n", [$title, '', ...session_text($b, $start, false), '', $first, $money,
        ...($refundOnly ? [] : ['', 'Vybrat jiný termín: ' . $book]), ...text_foot()]);
    $html = mail_page($subject, $title, session_panel($b, $start, false),
        [h($first) . ' ' . h($money), $refundOnly ? '' : mail_link('Vybrat jiný termín', $book)], mail_tint($b));
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => (string)$b['email']];
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
    $rel = 'rel-' . bin2hex(random_bytes(8));
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
        'Content-Type: multipart/related; boundary="' . $rel . '"',
        '',
        '--' . $rel,
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
        '--' . $rel,
        'Content-Type: image/png; name="khg.png"',
        'Content-ID: <' . BRAND_CID . '>',
        'Content-Disposition: inline; filename="khg.png"',
        'Content-Transfer-Encoding: base64',
        '', $b64(brand_png()),
        '--' . $rel . '--',
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
