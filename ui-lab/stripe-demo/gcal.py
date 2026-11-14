"""Google Calendar helper for the local booking demo (lab only).

Uses the owner's refresh token from ~/.config/khg/google-token.json (scopes: calendar.app.created, calendar.freebusy).
Writes ONLY to the demo's own calendar "KHG test rezervace"; the owner's real calendars are only read as busy/free.
"""
import json, os, time, urllib.error, urllib.parse, urllib.request, uuid

CFG = os.path.expanduser('~/.config/khg')
_env = dict(l.strip().split('=', 1) for l in open(os.path.join(CFG, 'google-oauth.env')) if '=' in l and not l.startswith('#'))
_tok = json.load(open(os.path.join(CFG, 'google-token.json')))
_cache = {'access': None, 'until': 0}
CAL_FILE = os.path.join(CFG, 'google-test-calendar.json')
TZ = 'Europe/Prague'


def _access():
    if time.time() < _cache['until'] - 60:
        return _cache['access']
    body = urllib.parse.urlencode({'client_id': _env['GOOGLE_CLIENT_ID'], 'client_secret': _env['GOOGLE_CLIENT_SECRET'],
                                   'refresh_token': _tok['refresh_token'], 'grant_type': 'refresh_token'}).encode()
    t = json.load(urllib.request.urlopen('https://oauth2.googleapis.com/token', body, timeout=20))
    _cache.update(access=t['access_token'], until=time.time() + t['expires_in'])
    return _cache['access']


def api(method, path, body=None, **query):
    url = 'https://www.googleapis.com/calendar/v3/' + path + ('?' + urllib.parse.urlencode(query) if query else '')
    req = urllib.request.Request(url, method=method, data=json.dumps(body).encode() if body is not None else None,
                                 headers={'Authorization': 'Bearer ' + _access(), 'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            raw = r.read()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        raise RuntimeError('%s %s: %s' % (e.code, path, e.read().decode()[:300]))


def test_calendar():
    """The demo's own calendar; created once, its id kept outside the repo."""
    if os.path.exists(CAL_FILE):
        return json.load(open(CAL_FILE))['id']
    cal = api('POST', 'calendars', {'summary': 'KHG test rezervace', 'timeZone': TZ,
                                    'description': 'Testovací kalendář pro vlastní rezervace (demo). Smí se smazat.'})
    fd = os.open(CAL_FILE, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'w') as f:
        json.dump({'id': cal['id']}, f)
    return cal['id']


def busy(start_iso, end_iso, calendars=('primary',)):
    r = api('POST', 'freeBusy', {'timeMin': start_iso, 'timeMax': end_iso, 'timeZone': TZ, 'items': [{'id': c} for c in calendars]})
    return {c: v.get('busy', []) for c, v in r['calendars'].items()}, {c: v.get('errors') for c, v in r['calendars'].items() if v.get('errors')}


def create_session(start_iso, end_iso, summary, description, online, location=None, guest=None):
    """Event in the test calendar; online sessions get a Google Meet link. No e-mails are sent to anyone."""
    ev = {'summary': summary, 'description': description, 'start': {'dateTime': start_iso, 'timeZone': TZ},
          'end': {'dateTime': end_iso, 'timeZone': TZ}, 'reminders': {'useDefault': True}}
    if location:
        ev['location'] = location
    if online and guest:
        # the client as a guest: signed in under this e-mail they join Meet straight away, like with Google's booking pages.
        # Anyone else with the link still has to ask to join. sendUpdates=none: no Google invite, our own e-mail does it.
        ev['attendees'] = [{'email': guest}]
        ev['guestsCanSeeOtherGuests'] = False
        ev['guestsCanInviteOthers'] = False
    if online:
        ev['conferenceData'] = {'createRequest': {'requestId': uuid.uuid4().hex, 'conferenceSolutionKey': {'type': 'hangoutsMeet'}}}
    return api('POST', 'calendars/%s/events' % urllib.parse.quote(test_calendar()), ev, conferenceDataVersion=1, sendUpdates='none')


def delete_session(event_id):
    api('DELETE', 'calendars/%s/events/%s' % (urllib.parse.quote(test_calendar()), event_id), sendUpdates='none')
