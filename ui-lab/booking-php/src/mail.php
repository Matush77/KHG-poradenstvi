<?php
/* Czech dates and the confirmation e-mail (plain text, HTML, .ics). Client-facing texts use "vy".
   Byte-safe without mbstring: UTF-8 is only split on character boundaries (preg /u). */
declare(strict_types=1);

require_once __DIR__ . '/smtp.php';

const PLACE = 'Vítkovická 3299/3a, 702 00 Ostrava';
// route to her Google Business Profile, not to a bare address (owner, 14 Nov): the profile's name plus the
// address resolves to the business pin in Google Maps
const NAVIGATE = 'https://www.google.com/maps/dir/?api=1&destination=Mgr.+Krist%C3%BDna+Hricikov%C3%A1+Glattov%C3%A1%2C+V%C3%ADtkovick%C3%A1+3299%2F3a%2C+Ostrava';
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

/* ---- The look of every e-mail: the redesign's theme. Paper ground, navy text; the top of the e-mail is the
   site's booking panel (sky for online, peach for in person, radius 24) holding the Fraunces heading and the
   session, so it fills a phone's width like the site's panels do. Fonts are the site's own files: Apple Mail
   and iOS load them, Gmail and Outlook use the metric-matched fallbacks (Georgia, the system sans).
   The brand is the coral heart as a small PNG (assets/heart.png, cut from brand.png) sent inside the e-mail (cid), and
   the name and role as real text: Gmail's dark mode lightens text but not images, so a name drawn into the image
   went navy on black (owner's phone, 14 Nov).
   No greetings or sign-offs; the footer carries her name and contacts. */
const BRAND_CID = 'brand@khgporadenstvi.cz';
const MAIL_FONTS = 'https://khgporadenstvi.cz/fonts/';   // Fraunces is there once the redesign ships
const MAIL_FONT = "Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
const MAIL_SERIF = "Fraunces,Georgia,'Times New Roman',serif";

function brand_png(): string { return (string)file_get_contents(__DIR__ . '/../assets/heart.png'); }

function mail_tint(array $b): string { return ($b['forma'] ?? '') === 'osobne' ? '#F6E6DC' : '#E8EFF4'; }

/* Buttons per the theme guide: pill, 56 px, 18 px / 600, full column width. Coral for booking and the client's Meet button
   (owner, 14 Nov); every other action is navy, solid for the main one and outlined for the rest. */
function mail_btn(string $label, string $url, string $kind = 'navy'): string
{
    $look = ['coral' => 'background:#E8927C;border:1.5px solid #E8927C;color:#163A55',
        'navy' => 'background:#163A55;border:1.5px solid #163A55;color:#FFFFFF',
        'outline' => 'background:transparent;border:1.5px solid #163A55;color:#163A55'][$kind];
    return '<a href="' . h($url) . '" style="display:block;margin:0 0 8px;padding:15px 12px;border-radius:999px;text-align:center;'
        . 'font-weight:600;font-size:18px;line-height:24px;text-decoration:none;' . $look . '">' . h($label) . '</a>';
}

/* Small text (15 px): mute on paper. */
function mail_muted(string $html): string { return '<span style="color:#5E6E7C;font-size:15px">' . $html . '</span>'; }

/* In-text links: blue 500, underlined. */
function mail_link(string $label, string $url): string
{
    return '<a href="' . h($url) . '" style="color:#2D5A7B;font-weight:500;text-decoration:underline">' . h($label) . '</a>';
}

function mail_foot_row(array $items): string
{
    // no dots between items: where a mail app wraps the row (Seznam did), a dot was left hanging at the line end
    return implode(' ', array_map(fn($x) => '<span style="display:inline-block;white-space:nowrap;margin-right:16px">' . $x . '</span>', $items));
}

/* $panel: html inside the top panel, under the heading; $blocks: html paragraphs under the panel. Sizes from
   the guide: heading Fraunces 500 (34, 24 on a phone: the Gmail app enlarges it), panel radius 24 with 32 padding (24 on a phone),
   body 17, small 15, no row lines (the footer hairline is the one allowed). */
function mail_page(string $subject, string $title, string $panel, array $blocks, string $tint, string $footNote = '', bool $foot = true): string
{
    $p = fn(string $x) => '<div style="margin:24px 0 0;color:#44576A">' . $x . '</div>';
    $css = "@font-face{font-family:'Inter';src:url('" . MAIL_FONTS . "Inter/Inter-LatinExt-var.woff2') format('woff2');font-weight:300 700}"
        . "@font-face{font-family:'Fraunces';src:url('" . MAIL_FONTS . "Fraunces/Fraunces-500-Soft-cs.woff2') format('woff2');font-weight:400 700}"
        . '@media (min-width:600px){.wrap{padding:24px 16px 40px!important}.panel{padding:32px!important}.h1{font-size:34px!important}}';
    return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . h($subject) . '</title>'
        . '<style>' . $css . '</style></head>'
        . '<body style="margin:0;padding:0;background:#FBF8F3;color:#163A55;font:17px/1.6 ' . MAIL_FONT . '">'
        . '<div class="wrap" style="max-width:600px;margin:0 auto;padding:16px 12px 32px">'
        // the site header's brand: heart, then the name (Fraunces 500 19) over the role (Inter 500 10.5, caps, tracked)
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 4px;border-collapse:collapse"><tr>'
        . '<td style="padding:0 10px 0 0;vertical-align:middle"><img src="cid:' . BRAND_CID . '" width="26" height="26" alt="" style="display:block;border:0;width:26px;height:26px"></td>'
        . '<td style="vertical-align:middle"><a href="https://khgporadenstvi.cz" style="text-decoration:none;color:#163A55">'
        . '<span style="display:block;font:500 19px/1.15 ' . MAIL_SERIF . ';letter-spacing:-.005em;color:#163A55;white-space:nowrap">' . SENDER . '</span>'
        . '<span style="display:block;margin-top:4px;font:500 10.5px/1 ' . MAIL_FONT . ';letter-spacing:.14em;text-transform:uppercase;color:#5E6E7C">Psycholog a terapeut</span>'
        . '</a></td></tr></table>'
        . '<div class="panel" style="background:' . $tint . ';border-radius:24px;padding:24px 20px">'
        . '<h1 class="h1" style="margin:0 0 24px;font:500 24px/1.15 ' . MAIL_SERIF . ';letter-spacing:-.01em;color:#163A55">' . h($title) . '</h1>'
        . $panel . '</div>'
        . '<div style="padding:0 4px">' . implode('', array_map($p, array_filter([...$blocks, $footNote ? mail_muted(h($footNote)) : ''])))
        // footer in three short rows that fit one line each even where an app shows it at body size (Seznam):
        // name, then phone and web, then the e-mail (owner, 14 Nov)
        . (!$foot ? '' : '<div style="margin-top:40px;padding-top:16px;border-top:1px solid rgba(22,58,85,.14);color:#5E6E7C;font-size:15px;line-height:1.7">'
        . '<span style="color:#163A55;font-weight:600">' . SENDER . '</span><br>'
        . mail_foot_row(['<a href="' . PHONE_HREF . '" style="color:#5E6E7C">' . PHONE . '</a>',
            '<a href="https://khgporadenstvi.cz" style="color:#5E6E7C">khgporadenstvi.cz</a>']) . '<br>'
        . '<a href="mailto:' . OWNER_MAIL . '" style="color:#5E6E7C">' . OWNER_MAIL . '</a></div>')
        . '</div></div></body></html>';
}

/* 'Úterý 24. listopadu' (no year: it fits one line with the time on a phone) */
function cz_day_short(DateTimeImmutable $d): string
{
    return ucfirst_utf8(CZ_DAYS[(int)$d->format('w')]) . ' ' . $d->format('j') . '. ' . CZ_MONTHS[(int)$d->format('n') - 1];
}

/* '1 100 Kč' from the config, with no-break spaces so the amount never splits across lines */
function price_kc(): string { return number_format(cfg()['price'] / 100, 0, ',', "\u{00A0}") . "\u{00A0}Kč"; }

function session_meta(array $b): string { return ($b['forma'] !== 'osobne' ? 'Online' : 'Osobně') . ' · 50 minut · ' . price_kc(); }

/* The session inside the format card: when (card heading), what (slate), and (when $action) where to go:
   the Meet button (coral, owner 14 Nov) or the address. */
function session_panel(array $b, DateTimeImmutable $start, bool $action, ?string $dayTitle = null): string
{
    $online = $b['forma'] !== 'osobne';
    $hours = hm($start) . '–' . hm($start->modify('+50 minutes'));
    $html = '<div style="font-size:20px;line-height:1.35;font-weight:600;color:#163A55">' . h($dayTitle ?? cz_day_short($start))
        . ', <span style="white-space:nowrap">' . $hours . '</span></div>'
        . '<div style="margin-top:4px;color:#44576A;font-size:15px">' . session_meta($b) . '</div>';
    if (!$action) return $html;
    $meet = (string)($b['meet'] ?? '');
    return $html . ($online
        // no link yet (Google still making it): never a button that leads nowhere (16 Nov review)
        ? ($meet !== '' ? '<div style="margin-top:24px">' . mail_btn('Připojit se k videohovoru', $meet, 'coral') . '</div>'
            : '<div style="margin-top:16px">' . mail_muted(h(MEET_LATER)) . '</div>')
        // the address is our own link to the same route, plain-looking: left as text, Gmail and Seznam linked it
        // themselves (to a map search), sometimes only half of it
        : '<div style="margin-top:16px;color:#163A55"><a href="' . h(NAVIGATE) . '" style="color:#163A55;text-decoration:none">'
            . h(PLACE) . '</a><br>' . mail_link('Navigovat', NAVIGATE) . '</div>');
}

/* Plain-text twin of the panel. */
function session_text(array $b, DateTimeImmutable $start, bool $action): array
{
    $online = $b['forma'] !== 'osobne';
    $lines = [cz_day_short($start) . ', ' . hm($start) . '–' . hm($start->modify('+50 minutes')), session_meta($b)];
    if ($action) $lines[] = $online ? (($b['meet'] ?? '') ? 'Připojit se: ' . $b['meet'] : MEET_LATER) : PLACE . "\nNavigace: " . NAVIGATE;
    return $lines;
}

const MEET_LATER = 'Odkaz na videohovor přijde e-mailem v připomínce před sezením.';

function text_foot(): array { return ['', '—', SENDER . ' · ' . PHONE . ' · ' . OWNER_MAIL . ' · khgporadenstvi.cz']; }

const ICS_PRAGUE = ['BEGIN:VTIMEZONE', 'TZID:Europe/Prague',
    'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000',
    'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
    'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000',
    'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD', 'END:VTIMEZONE'];

/* The client's confirmation, and "Změna termínu" after a move ($old: the previous start). Plain text, HTML, .ics. */
function mail_build(array $b, DateTimeImmutable $start, string $cancelUrl, ?DateTimeImmutable $old = null, bool $byClient = false): array
{
    $online = $b['forma'] !== 'osobne';
    $end = $start->modify('+50 minutes');
    $meet = (string)($b['meet'] ?? '');
    $email = (string)($b['email'] ?? '');
    $subject = ($old ? 'Změna termínu: sezení ' : 'Potvrzení: sezení ') . cz_at($start);
    $title = $old ? 'Změna termínu' : 'Potvrzení rezervace';
    // who moved it (16 Nov test run): a client who did not move it must not wonder why the time changed
    $oldTxt = $old ? ($byClient ? '' : 'Termín přesunula ' . SENDER . '. ') . 'Původní termín ' . cz_at($old) . ' už neplatí.' : '';
    // the amount from the config, never from the browser; on a move nothing new is paid, so only the confirmation
    $paid = $old ? '' : 'Platba ' . price_kc() . ' byla přijata.';
    $rule = 'Přesunout nebo zdarma zrušit lze nejpozději 36 hodin před sezením. Později se platba nevrací.';
    $linkLabel = 'Přesunout nebo zrušit termín';

    $text = implode("\n", [$title, '', ...session_text($b, $start, true), ...($old ? [$oldTxt] : []), ...($paid ? [$paid] : []), '',
        $rule, $linkLabel . ': ' . $cancelUrl, ...text_foot()]);
    $html = mail_page($subject, $title, session_panel($b, $start, true), [
        $old ? h($oldTxt) : '',
        // the paid amount stands out (owner, 14 Nov): navy like the panel, the amount in 600 like the session time
        $paid ? '<span style="color:#163A55">Platba <b style="font-weight:600">' . h(price_kc()) . '</b> byla přijata.</span>' : '',
        h($rule) . '<br>' . mail_link($linkLabel, $cancelUrl),
    ], mail_tint($b), 'V příloze je termín do kalendáře.');

    // METHOD:PUBLISH, not REQUEST: a plain "add to calendar" file, no invitation/RSVP flow in the client's mail app
    $f = fn(DateTimeImmutable $d) => $d->format('Ymd\THis');
    $desc = ($online && $meet ? "Připojit se: $meet\n" : '') . $linkLabel . ': ' . $cancelUrl;
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//khgporadenstvi.cz//rezervace//CS', 'METHOD:PUBLISH',
        ...ICS_PRAGUE,
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
    if ($hours < 48) return max(0, (int)floor($hours)) . ' h';   // 35.96 h reads 35, never 36 (17 Nov review, second list)
    $d = (int)round($hours / 24);
    return $d . ($d < 5 ? ' dny' : ' dní');
}

/* 'st 16. 12. v 14:00' for subjects */
function cz_short(DateTimeImmutable $d): string
{
    return ['ne', 'po', 'út', 'st', 'čt', 'pá', 'so'][(int)$d->format('w')] . ' ' . $d->format('j. n.') . ' v ' . hm($d);
}

/* ---- Kristýna's own e-mails: plain on purpose (owner, 17 Nov: they serve a function, and she reads them in dark mode).
   No colours at all, no background, no image, no web fonts: the text, links and borders take the mail app's own
   theme, so Gmail, Apple Mail and Seznam show them right in light and in dark without inverting anything we set.
   Hierarchy by size and weight only; text 15 px (owner, 17 Nov: a bit smaller), buttons stay 17 px. The one fixed colour is the button border, a mid grey that shows on white and
   on black. Facts first (when, who, where), then what happened, then the actions as large tap targets. */
const OWNER_FONT = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

/* Filled for the action the e-mail is about (owner, 17 Nov: highlight them): Meet in deep coral, the rest in blue 500, both
   with white text. Both are mid-tones, so a button shows on white and on
   black, and an app that inverts colours (Gmail on iPhone) still keeps text and ground apart. The second action
   (refund) is outlined, so the eye and a thumb land on the first one. */
function owner_btn(string $label, string $url, string $kind = 'fill'): string
{
    // the colour sits on a table cell (bgcolor too), not on the link: Gmail dropped a link's background and left
    // white text on white (17 Nov test). Text on a filled button is white: Gmail's dark mode turned navy text light
    // on the coral (17 Nov, owner's phone), white it leaves alone. So the coral is a deeper one, 4.5:1 with white.
    [$bg, $fg] = ['fill' => ['#2D5A7B', '#FFFFFF'], 'meet' => ['#B85C44', '#FFFFFF'], 'outline' => ['', '']][$kind];
    $cell = $bg ? ' bgcolor="' . $bg . '" style="background-color:' . $bg . ';border:2px solid ' . $bg . ';border-radius:10px"'
        : ' style="border:2px solid #8C8C8C;border-radius:10px"';
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:separate;margin:0 0 10px">'
        . '<tr><td align="center"' . $cell . '><a href="' . h($url) . '" style="display:block;padding:13px 12px;' . ($fg ? 'color:' . $fg . ';' : '')
        . 'text-align:center;font-weight:700;font-size:17px;line-height:22px;text-decoration:none">' . h($label) . '</a></td></tr></table>';
}

function owner_small(string $html): string { return '<span style="font-size:13px;line-height:1.5">' . $html . '</span>'; }

/* $top: the facts (when, client); $blocks: paragraphs and buttons, each its own block. */
function owner_page(string $subject, string $title, string $top, array $blocks): string
{
    $p = fn(string $x) => '<div style="margin:0 0 18px">' . $x . '</div>';
    return '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark"><title>' . h($subject) . '</title></head>'
                // the type sits on the wrapper: Gmail drops the body's style (its 13 px default took over, 17 Nov)
        . '<body style="margin:0;padding:0">'
        . '<div style="max-width:560px;margin:0 auto;padding:16px 16px 24px;font-family:' . OWNER_FONT . ';font-size:15px;line-height:1.5">'
        . '<h1 style="margin:0 0 14px;font-size:21px;line-height:1.25;font-weight:700">' . h($title) . '</h1>'
        . $p($top) . implode('', array_map($p, array_filter($blocks))) . '</div></body></html>';
}

/* The facts at the top of every owner e-mail: when (large), what, then the client with contacts. [html, text lines] */
function owner_panel(array $b, DateTimeImmutable $start): array
{
    $who = (string)($b['name'] ?: 'bez jména');
    $hours = hm($start) . '–' . hm($start->modify('+50 minutes'));
    $html = '<div style="font-size:17px;line-height:1.35;font-weight:700">' . h(cz_day_short($start)) . ', <span style="white-space:nowrap">' . $hours . '</span></div>'
        . '<div>' . h(session_meta($b)) . '</div>'
        . '<div style="margin-top:14px"><b>' . h($who) . '</b>'
        . (!empty($b['email']) ? '<br><a href="mailto:' . h($b['email']) . '">' . h($b['email']) . '</a>' : '')
        . (!empty($b['phone']) ? '<br><a href="tel:' . h(preg_replace('/[^\d+]/', '', $b['phone'])) . '">' . h($b['phone']) . '</a>' : '') . '</div>';
    return [$html, [...session_text($b, $start, false), $who, ...array_filter([$b['email'] ?? null, $b['phone'] ?? null])]];
}

/* Owner e-mails share this: title, the facts, paragraphs, buttons [label => url]. */
function owner_mail(array $b, DateTimeImmutable $start, string $subject, string $title, array $paras, array $buttons = [], array $textExtra = []): array
{
    [$panel, $panelTxt] = owner_panel($b, $start);
    $btns = '';
    foreach ($buttons as $label => $url) $btns .= owner_btn($label, $url, $btns === '' ? 'fill' : 'outline');
    $html = owner_page($subject, $title, $panel, [...array_map('h', $paras), $btns]);
    $text = implode("\n", [$title, '', ...$panelTxt, '', ...$paras, ...$textExtra,
        ...array_map(fn($l, $u) => "$l: $u", array_keys($buttons), $buttons)]);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
}

/* The calendar event, like Google's booking pages write it. Description in Google's simple HTML (bold headings). */
const ARRIVE_NOTE = 'Terapie v prostorách ordinace. Prosím, přicházejte a zvoňte až v čase vašeho objednaného sezení. Děkuji, že tento čas respektujete.';
function event_title(array $b): string
{
    return 'Terapie ' . ($b['forma'] !== 'osobne' ? 'online' : 'osobně') . ' (' . ($b['name'] ?: 'klient') . ')';
}
function event_description(array $b): string
{
    $block = fn(string $head, array $lines) => '<b>' . h($head) . '</b><br>' . implode('<br>', array_map('h', $lines));
    $note = trim((string)($b['note'] ?? ''));
    return implode('<br><br>', array_filter([
        $block('Rezervoval(a)', array_values(array_filter([$b['name'] ?: 'bez jména', $b['email'] ?? '', $b['phone'] ?? '']))),
        $note !== '' ? $block('S čím přicházíte?', explode("\n", $note)) : '',
        $block('Zaplacená částka', [number_format(cfg()['price'] / 100, 2, ',', "\u{00A0}") . "\u{00A0}Kč"]),
        $b['forma'] === 'osobne' ? h(ARRIVE_NOTE) : '',
        lab() ? h('Test webu, Stripe test.') : '',
    ]));
}

/* Her copy of the session as a calendar file, so Gmail shows its event card at the top of "Schůzka byla rezervována".
   The UID is the Google event's own (iCalUID of an event made with our id), so Gmail sees the event she already has
   instead of offering a second one. PUBLISH: nothing to answer, nothing added on its own. */
function owner_ics(array $b, DateTimeImmutable $start): ?string
{
    if (empty($b['event_id'])) return null;
    $online = $b['forma'] !== 'osobne';
    $f = fn(DateTimeImmutable $d) => $d->format('Ymd\THis');
    $lines = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//khgporadenstvi.cz//rezervace//CS', 'METHOD:PUBLISH',
        ...ICS_PRAGUE,
        'BEGIN:VEVENT', 'UID:' . $b['event_id'] . '@google.com',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'SEQUENCE:' . (int)($b['moves'] ?? 0),
        'DTSTART;TZID=Europe/Prague:' . $f($start), 'DTEND;TZID=Europe/Prague:' . $f($start->modify('+50 minutes')),
        'SUMMARY:' . ics_text((lab() ? 'TEST · ' : '') . event_title($b)),
        'LOCATION:' . ics_text($online ? (string)($b['meet'] ?? '') : PLACE),
        'DESCRIPTION:' . ics_text(implode("\n", array_filter([$b['name'] ?: '', $b['email'] ?? '', $b['phone'] ?? '']))),
        'END:VEVENT', 'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('ics_fold', $lines)) . "\r\n";
}

/* To the owner, for every new booking: who, when, what they wrote, and the two buttons. */
function mail_owner_new(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    $online = $b['forma'] !== 'osobne';
    // worded like Google's booking pages (owner, 16 Nov), so her inbox filters and habits keep working
    $subject = 'Schůzka byla rezervována: Terapie ' . ($online ? 'online' : 'osobně') . ' (' . ($b['name'] ?: 'klient') . ') ' . cz_at($start);
    $note = trim((string)($b['note'] ?? ''));
    $buttons = ['Přesunout termín' => $ownerUrl . '&akce=presunout', 'Zrušit a vrátit peníze' => $ownerUrl . '&akce=zrusit'];
    [$panel, $panelTxt] = owner_panel($b, $start);
    // where the session is (owner, 16 Nov): the Meet link or the address, as Google's e-mail has "Lokalita"
    $meet = (string)($b['meet'] ?? '');
    $where = $online ? ($meet !== '' ? owner_btn('Připojit se přes Google Meet', $meet, 'meet') : '')
        : h(PLACE) . '<br><a href="' . h(NAVIGATE) . '">Navigovat</a>';
    $whereTxt = $online ? ($meet !== '' ? 'Google Meet: ' . $meet : null) : PLACE;
    $html = owner_page($subject, 'Nová rezervace', $panel, [
        !empty($b['clash']) ? '<b>⚠️ V kalendáři je ve stejnou dobu i jiná událost.</b>' : '',
        $where,
        $note !== '' ? '<b>S čím přichází</b><br>' . nl2br(h($note)) : '',
        !$online && empty($b['email']) ? 'Klient nechal jen telefon, potvrzení e-mailem nedostal.' : '',
        owner_btn('Přesunout termín', $buttons['Přesunout termín']) . owner_btn('Zrušit a vrátit peníze', $buttons['Zrušit a vrátit peníze'], 'outline'),
        owner_small('Odkazy fungují bez přihlášení, e-mail proto nepřeposílej.'),
    ]);
    $text = implode("\n", array_filter(['Nová rezervace', '', ...$panelTxt, '',
        $whereTxt !== null ? $whereTxt . "\n" : null,
        $note !== '' ? "S čím přichází:\n$note\n" : null,
        !empty($b['clash']) ? "V kalendáři je ve stejnou dobu i jiná událost.\n" : null,
        'Přesunout termín: ' . $buttons['Přesunout termín'],
        'Zrušit a vrátit peníze: ' . $buttons['Zrušit a vrátit peníze'],
    ], fn($l) => $l !== null));
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => owner_ics($b, $start), 'to' => OWNER_MAIL, 'reply_to' => $b['email'] ?: null];
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

/* To the owner when Stripe refused a refund (at once or later): the client was told the money comes back. */
function mail_owner_refund_failed(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    // what Stripe says (17 Nov review, second list: a part failed, the refund vanished, it waits for an action)
    $what = match ($b['refund_status']) {
        'partial' => 'Ve Stripe se z 1 100 Kč vrátila jen část, zbytek se klientovi nevrátil.',
        'missing' => 'Vrácení 1 100 Kč ve Stripe není, peníze se klientovi nevracejí.',
        'requires_action' => 'Vrácení 1 100 Kč čeká ve Stripe na doplnění údajů, peníze zatím neodešly.',
        default => 'Stripe vrácení 1 100 Kč odmítl, peníze se klientovi nevrátily.',
    };
    return owner_mail($b, $start, 'Vrácení peněz selhalo: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Vrácení peněz selhalo',
        [$what, 'Zkus to tlačítkem znovu (vrátí se, co chybí), nebo to vyřeš ve Stripe (Platby → tahle platba) a klientovi napiš.'],
        ['Vrátit peníze znovu' => $ownerUrl . '&akce=zrusit']);
}

/* To the owner when Stripe shows the payment refunded although the site did not refund it (her Stripe dashboard):
   the session still booked, or a payment that was refunded before the site booked it (17 Nov review, second list). */
function mail_owner_refund_found(array $b, DateTimeImmutable $start, string $ownerUrl): array
{
    if ($b['status'] !== 'booked') {
        return owner_mail($b, $start, 'Vrácená platba se nezarezervovala: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Platba už byla vrácená',
            ['Platba za tenhle termín se k webu dostala až po tom, co byla ve Stripe vrácená. Rezervace proto nevznikla, termín je volný a klient od webu nic nedostal.',
             'Kdyby měl klient přesto přijít, ozvi se mu.']);
    }
    return owner_mail($b, $start, 'Platba vrácená, sezení ale platí: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Platba vrácená, sezení ale platí',
        ['Ve Stripe je tahle platba vrácená, rezervace na webu ale dál platí a sezení je v kalendáři.',
         'Pokud se nekoná, zruš ho tlačítkem: událost zmizí, termín se uvolní a klient dostane e-mail. Peníze se podruhé nevracejí.'],
        ['Zrušit sezení' => $ownerUrl . '&akce=zrusit']);
}

/* To the owner when two payments arrived for one time (should never happen): the later one was refunded. */
function mail_owner_double(array $b, DateTimeImmutable $start, bool $refunded): array
{
    if (($b['cancelled_by'] ?? '') === 'missed') {
        return owner_mail($b, $start, 'Platba za termín, který už proběhl: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Platba za termín, který už proběhl',
            [($refunded ? 'Platba přišla, ale web ji kvůli výpadku zpracoval až po začátku sezení. Rezervace nevznikla, 1 100 Kč se klientovi vrací a dostal e-mail.'
                : 'Platba přišla, ale web ji kvůli výpadku zpracoval až po začátku sezení. Rezervace nevznikla, ale vrácení peněz se zatím nepovedlo: vyřeš ho prosím ve Stripe.'),
             'Klient mohl na sezení čekat: ozvi se mu prosím.']);
    }
    return owner_mail($b, $start, 'Dvě platby za jeden termín: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Dvě platby za jeden termín',
        [($refunded ? 'Tenhle klient zaplatil termín, který už měl někdo jiný. Rezervace nevznikla, 1 100 Kč se mu vrací a dostal e-mail.'
            : 'Tenhle klient zaplatil termín, který už měl někdo jiný. Rezervace nevznikla, ale vrácení peněz se zatím nepovedlo: vyřeš ho prosím ve Stripe.'),
         'Kdyby chtěl jiný termín, ozve se, nebo mu napiš.']);
}

/* To the owner when a paid booking still has no calendar event after half an hour (Google refusing): the time is
   held, the site keeps trying, and the money goes back by itself if it never works before the session. */
function mail_owner_calendar_failed(array $b, DateTimeImmutable $start): array
{
    return owner_mail($b, $start, 'Rezervace se nezapsala do kalendáře: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'Rezervace se nezapsala do kalendáře',
        ['Klient zaplatil, ale zápis do kalendáře se zatím nepovedl (Google nebo platební brána Stripe neodpovídá). Klient zatím nedostal potvrzení.',
         'Termín je pro klienta držený, nikdo jiný ho nezarezervuje. Web to zkouší dál každých pár minut; jakmile se to povede, přijde ti obvyklá „Nová rezervace“ a klientovi potvrzení.',
         'Kdyby se to do začátku sezení nepovedlo, platba se klientovi vrátí sama a oba dostanete e-mail.']);
}

/* To the client whose payment came for a time someone else had just booked: no booking, the money goes back. */
function mail_client_double(array $b, DateTimeImmutable $start): array
{
    $book = site() . cfg()['booking_page'];
    $missed = ($b['cancelled_by'] ?? '') === 'missed';
    $subject = $missed ? 'Rezervace ' . cz_at($start) . ' se nedokončila, platba se vrací' : 'Termín ' . cz_at($start) . ' se mezitím obsadil';
    $title = $missed ? 'Rezervace se nedokončila' : 'Termín se mezitím obsadil';
    $money = ($missed ? 'Platba proběhla, ale kvůli technické chybě webu se rezervace nezapsala včas, před začátkem sezení. ' : '')
        . 'Platba za tenhle termín se vrací celá na kartu použitou k platbě, obvykle do 5–10 pracovních dnů. Omlouvám se za komplikaci.';
    $text = implode("\n", [$title, '', ...session_text($b, $start, false), '', $money, '', 'Vybrat jiný termín: ' . $book, ...text_foot()]);
    $html = mail_page($subject, $title, session_panel($b, $start, false), [h($money), mail_btn('Vybrat jiný termín', $book, 'coral')], mail_tint($b));
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => (string)$b['email']];
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

/* To the owner when a client's e-mail could not be delivered: the address refused it, or it kept failing for days. */
const MAIL_WHAT = ['confirmation' => 'potvrzení rezervace', 'client_cancelled' => 'zrušení sezení', 'client_cancel' => 'zrušení sezení',
    'client_refund' => 'vrácení platby', 'client_double' => 'vrácení platby za obsazený termín'];
function mail_owner_undeliverable(array $b, DateTimeImmutable $start, string $ownerUrl, string $what): array
{
    $addr = (string)($b['email'] ?? '');
    return owner_mail($b, $start, 'E-mail klientovi nedorazil: ' . ($b['name'] ?: 'klient') . ' · ' . cz_short($start), 'E-mail klientovi nedorazil',
        ['E-mail „' . (MAIL_WHAT[$what] ?? 'zpráva z webu') . '“ na adresu ' . $addr . ' se nepodařilo doručit, klient ho nemá.',
         $b['phone'] ? 'Dej mu prosím vědět jinak, třeba telefonem.' : 'Dej mu prosím vědět jinak.'],
        ['Otevřít rezervaci' => $ownerUrl]);
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
        $title = 'Začátek za 10 minut';
    } else {
        $subject = 'Připomínka: sezení ' . ($dayWord ? $dayWord . ' v ' . hm($start) : cz_at($start));
        $title = 'Připomínka';                                       // the panel right under it says when
    }
    $panelDay = $dayWord ? ucfirst_utf8($dayWord) : null;
    // the cancel link only while free cancelling is still possible (owner, 14 Nov: none under 36 hours); the
    // day-before reminder goes out 24 hours ahead, so with today's settings it never has one
    $hoursLeft = ($start->getTimestamp() - $today->getTimestamp()) / 3600;
    $cancel = $kind === 'day' && $hoursLeft >= cfg()['free_cancel_hours'] ? cancel_url($b['pay_id']) : '';
    $html = mail_page($subject, $title, session_panel($b, $start, true, $panelDay),
        [$cancel ? mail_link('Zrušit termín', $cancel) : ''], mail_tint($b));
    $text = implode("\n", [$title, '', ...session_text($b, $start, true), ...($cancel ? ['', 'Zrušit termín: ' . $cancel] : []), ...text_foot()]);
    return ['subject' => $subject, 'text' => $text, 'html' => $html, 'ics' => null, 'to' => (string)($b['email'] ?? '')];
}

/* To the client when the owner cancels (full refund), or refunds a session the client cancelled late. */
/* $refunded false only when the client cancelled late themself (no refund): the money line says so instead.
   $delayed: the refund was started but Stripe refused it (she is told separately and sorts it out): the e-mail
   neither promises the money nor says it is not coming back (review 17 Nov). */
function mail_client_cancelled(array $b, DateTimeImmutable $start, bool $refundOnly, bool $refunded = true, bool $byOwner = false, bool $delayed = false): array
{
    $book = site() . cfg()['booking_page'];
    $subject = $refundOnly ? 'Vrácení platby za sezení ' . cz_at($start) : 'Zrušené sezení ' . cz_at($start);
    // passive, no "I" or "you" (owner, 14 Nov): one sentence about the money, the same in both
    $title = $refundOnly ? 'Vrácení platby' : 'Zrušení sezení';
    // she cancelled (16 Nov test run: her page promised "Klient dostane e-mail, že sezení rušíš", the e-mail did not say so)
    $who = $byOwner ? 'Sezení zrušila ' . SENDER . '.' : '';
    $money = $delayed ? 'Vrácení platby se zdrželo a řeší se. Jakmile bude vyřízené, přijde další e-mail.'
        : ($refunded ? 'Částka bude vrácena na kartu použitou k platbě, obvykle do 5–10 pracovních dnů.'
        : 'Platba se nevrací, termín byl zrušen méně než ' . cfg()['free_cancel_hours'] . ' hodin předem.');
    $text = implode("\n", [$title, '', ...session_text($b, $start, false), '', ...($who ? [$who] : []), $money,
        ...($refundOnly ? [] : ['', 'Vybrat jiný termín: ' . $book]), ...text_foot()]);
    $html = mail_page($subject, $title, session_panel($b, $start, false),
        [$who ? h($who) : '', h($money), $refundOnly ? '' : mail_btn('Vybrat jiný termín', $book, 'coral')], mail_tint($b));
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
    $brand = str_contains($m['html'], 'cid:' . BRAND_CID);   // her own e-mails have no image
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
        ...($brand ? ['Content-Type: multipart/related; boundary="' . $rel . '"', '', '--' . $rel] : []),
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
        ...($brand ? ['--' . $rel,
        'Content-Type: image/png; name="srdce.png"',
        'Content-ID: <' . BRAND_CID . '>',
        'Content-Disposition: inline; filename="srdce.png"',
        'Content-Transfer-Encoding: base64',
        '', $b64(brand_png()),
        '--' . $rel . '--',
        ''] : []),
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
    // the tests stand in for the mail server here (command line only, like HTTP_FAKE)
    $fake = PHP_SAPI === 'cli' && isset($GLOBALS['SMTP_FAKE']) ? $GLOBALS['SMTP_FAKE'] : null;
    if (!$fake) {
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
    }
    if ($until = mail_paused()) throw new RuntimeException('SMTP paused until ' . date('H:i', $until) . ' (server asked to wait)');
    try {
        $fake ? $fake($name, $m) : smtp_send($c['smtp'], FROM_MAIL, $m['to'], mail_eml($m));
    } catch (RuntimeException $e) {
        // the server asks to wait (a sending limit, or Google's "try again later"; 16 Nov: the cron retried every
        // 5 minutes for two days and the limit never cleared): stop talking to it for a while, the safety net
        // sends what is waiting afterwards. Such a refusal is never taken as a refusal for good.
        if ($minutes = smtp_backoff($e->getMessage())) {
            state_set('mail_paused_until', (string)(time() + $minutes * 60));
            log_line('MAIL_PAUSED', ['minutes' => $minutes, 'error' => substr($e->getMessage(), 0, 200)]);
            if ($e instanceof SmtpRejected) throw new RuntimeException($e->getMessage(), 0, $e);
        }
        throw $e;
    }
    log_line('SMTP_SENT', ['mail' => $name]);
}

/* After the mail server asked to wait, nothing is sent for a while. Returns the end of the pause (a timestamp),
   or 0 when sending is allowed.
   smtp_backoff: how long to wait after this error, 0 for none. A limit (Google: "550 5.4.5 Daily SMTP relay limit
   exceeded", the old mailbox: "550 ... daily limit") waits an hour; Google's "come back later" answers (421, 454,
   4.7.x, e.g. "454 4.7.0 Too many login attempts") a quarter of an hour (review 17 Nov: the 454 had no "limit" in
   it and was retried every run). A wrong password or a refused address is not a reason to wait. */
const MAIL_PAUSE_MINUTES = 60, MAIL_BACKOFF_MINUTES = 15;
function smtp_backoff(string $error): int
{
    if (!preg_match('/\b([45])\d\d\b/', $error)) return 0;   // no answer from the server at all (a dead host): no pause
    if (preg_match('/limit|quota|too many|rate\b|5\.4\.5/i', $error)) return MAIL_PAUSE_MINUTES;
    if (preg_match('/got (421|454)\b|\b4\.7\.\d+|try again later/i', $error)) return MAIL_BACKOFF_MINUTES;
    return 0;
}
function smtp_over_limit(string $error): bool { return smtp_backoff($error) === MAIL_PAUSE_MINUTES; }
function mail_paused(): int
{
    $until = (int)(state_get('mail_paused_until') ?? 0);
    return $until > time() ? $until : 0;
}
