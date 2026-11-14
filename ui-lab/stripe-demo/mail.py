"""Confirmation e-mail for a paid booking (lab only; the real back end ports this to PHP one to one).

build() returns the subject, a plain-text body, an HTML body and an .ics file. No I/O here.
Client-facing texts use "vy", like the rest of the site. Times are Europe/Prague.
"""
import datetime, html

DAYS = ['pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle']
DAYS_UNTIL = ['pondělí', 'úterý', 'středy', 'čtvrtka', 'pátku', 'soboty', 'neděle']  # "do ..." takes the genitive
DAYS_IN = ['v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu', 'v neděli']
MONTHS = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince']
PLACE = 'Vítkovická 3299/3a, 702 00 Ostrava'
NAVIGATE = 'https://www.google.com/maps/dir/?api=1&destination=V%C3%ADtkovick%C3%A1+3299%2F3a%2C+702+00+Ostrava'
PHONE, PHONE_HREF = '+420 605 990 980', 'tel:+420605990980'
MAIL = 'kristyna@khgporadenstvi.cz'
SENDER = 'Kristýna Hriciková Glattová'


def at(dt):
    """'v úterý 22. prosince v 10:00'."""
    return '%s %d. %s v %d:%02d' % (DAYS_IN[dt.weekday()], dt.day, MONTHS[dt.month - 1], dt.hour, dt.minute)


def until(dt):
    """'pondělí 21. prosince 1:00', read after "do"."""
    return '%s %d. %s %d:%02d' % (DAYS_UNTIL[dt.weekday()], dt.day, MONTHS[dt.month - 1], dt.hour, dt.minute)


def day(dt):
    return '%s %d. %s %d' % (DAYS[dt.weekday()].capitalize(), dt.day, MONTHS[dt.month - 1], dt.year)


def _ics_time(dt):
    return dt.strftime('%Y%m%dT%H%M%S')


def _fold(line):
    """RFC 5545: lines longer than 75 bytes continue on the next line after a space (never inside a UTF-8 character)."""
    out, cur = [], b''
    for ch in line:
        b = ch.encode()
        if len(cur) + len(b) > (75 if not out else 74):
            out.append(cur.decode()); cur = b''
        cur += b
    out.append(cur.decode())
    return '\r\n '.join(out)


def _ics_text(s):
    return s.replace('\\', '\\\\').replace(';', '\\;').replace(',', '\\,').replace('\n', '\\n')


def build(meta, start, deadline, cancel_url, meet=None, pay_id=''):
    online = meta.get('forma') != 'osobne'
    end = start + datetime.timedelta(minutes=50)
    hours = '%d:%02d–%d:%02d' % (start.hour, start.minute, end.hour, end.minute)
    when = '%s, %s' % (day(start), hours)
    kind = 'Online (Google Meet)' if online else 'Osobně'
    email = meta.get('email') or ''
    subject = 'Potvrzení: sezení %s' % at(start)

    if online:
        where_txt = ('Odkaz na videohovor: %s\n'
                     'Když budete v Googlu přihlášení jako %s, pustí vás to rovnou. '
                     'Jinak požádáte o vstup a já vás pustím.' % (meet or '(doplní se)', email))
        where_html = ('<p style="margin:0 0 8px"><a href="%s" style="display:inline-block;background:#E8927C;color:#163A55;'
                      'font-weight:600;text-decoration:none;padding:12px 20px;border-radius:999px">Připojit se k videohovoru</a></p>'
                      '<p style="margin:0;color:#44576A;font-size:14px">Když budete v Googlu přihlášení jako %s, pustí vás to rovnou. '
                      'Jinak požádáte o vstup a já vás pustím.</p>' % (html.escape(meet or '#'), html.escape(email)))
    else:
        where_txt = 'Místo: %s\nNavigace: %s' % (PLACE, NAVIGATE)
        where_html = ('<p style="margin:0">%s<br><a href="%s" style="color:#2D5A7B">Navigovat</a></p>'
                      % (html.escape(PLACE), NAVIGATE))

    cancel_txt = ('Zrušit zdarma můžete do %s (36 hodin předem), vrátím celou částku. Později už se platba nevrací.\n'
                  'Zrušit termín: %s' % (until(deadline), cancel_url))
    text = '\n'.join([
        'Dobrý den,', '',
        'vaše sezení je zaplacené a rezervované.', '',
        when, kind + ' · 50 minut · 1 100 Kč', '',
        where_txt, '',
        cancel_txt, '',
        'Kdybyste cokoli potřebovali, napište mi na %s nebo zavolejte na %s.' % (MAIL, PHONE), '',
        'Těším se na vás', SENDER, 'khgporadenstvi.cz',
    ])

    tint = '#E8EFF4' if online else '#F6E6DC'
    body = (
        '<!doctype html><html lang="cs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        '<meta name="color-scheme" content="light"><title>%(subject)s</title></head>'
        '<body style="margin:0;background:#FBF8F3;color:#163A55;font:16px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif">'
        '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        '<p style="margin:0 0 16px">Dobrý den,</p>'
        '<p style="margin:0 0 16px">vaše sezení je zaplacené a rezervované.</p>'
        '<div style="background:%(tint)s;border-radius:16px;padding:16px 18px;margin:0 0 20px">'
        '<b style="display:block;font-size:18px">%(day)s, <span style="white-space:nowrap">%(hours)s</span></b><span style="color:#44576A">%(kind)s · 50 minut · 1 100 Kč</span></div>'
        '%(where)s'
        '<hr style="border:0;border-top:1px solid rgba(22,58,85,.14);margin:24px 0">'
        '<p style="margin:0 0 8px">Zrušit zdarma můžete do <b>%(deadline)s</b> (36 hodin předem), vrátím celou částku. '
        'Později už se platba nevrací.</p>'
        '<p style="margin:0 0 24px"><a href="%(cancel)s" style="color:#2D5A7B">Zrušit termín</a></p>'
        '<p style="margin:0 0 16px">Kdybyste cokoli potřebovali, napište mi na <a href="mailto:%(mail)s" style="color:#2D5A7B">%(mail)s</a> '
        'nebo zavolejte na <a href="%(tel)s" style="color:#2D5A7B">%(phone)s</a>.</p>'
        '<p style="margin:0">Těším se na vás<br>%(sender)s</p>'
        '<p style="margin:24px 0 0;color:#5E6E7C;font-size:13px">khgporadenstvi.cz · V příloze je termín do kalendáře.</p>'
        '</div></body></html>'
    ) % {'subject': html.escape(subject), 'tint': tint, 'when': html.escape(when), 'kind': kind, 'where': where_html,
         'deadline': html.escape(until(deadline)), 'day': html.escape(day(start)), 'hours': hours, 'cancel': html.escape(cancel_url), 'mail': MAIL, 'tel': PHONE_HREF,
         'phone': PHONE, 'sender': SENDER}

    # METHOD:PUBLISH, not REQUEST: a plain "add to calendar" file, no invitation/RSVP flow in the client's mail app
    loc = (meet or '') if online else PLACE
    ics = '\r\n'.join(_fold(l) for l in [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//khgporadenstvi.cz//rezervace//CS', 'METHOD:PUBLISH',
        'BEGIN:VTIMEZONE', 'TZID:Europe/Prague',
        'BEGIN:DAYLIGHT', 'TZOFFSETFROM:+0100', 'TZOFFSETTO:+0200', 'TZNAME:CEST', 'DTSTART:19700329T020000',
        'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU', 'END:DAYLIGHT',
        'BEGIN:STANDARD', 'TZOFFSETFROM:+0200', 'TZOFFSETTO:+0100', 'TZNAME:CET', 'DTSTART:19701025T030000',
        'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU', 'END:STANDARD', 'END:VTIMEZONE',
        'BEGIN:VEVENT', 'UID:%s@khgporadenstvi.cz' % (pay_id or start.isoformat()),
        'DTSTAMP:%s' % datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ'),
        'DTSTART;TZID=Europe/Prague:%s' % _ics_time(start), 'DTEND;TZID=Europe/Prague:%s' % _ics_time(end),
        'SUMMARY:%s' % _ics_text('Sezení s Kristýnou Hricikovou Glattovou'),
        'LOCATION:%s' % _ics_text(loc),
        'DESCRIPTION:%s' % _ics_text(('Připojit se: %s\n' % meet if online and meet else '') + 'Zrušit termín: %s' % cancel_url),
        'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:Sezení za hodinu', 'TRIGGER:-PT1H', 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR']) + '\r\n'
    return {'subject': subject, 'text': text, 'html': body, 'ics': ics}
