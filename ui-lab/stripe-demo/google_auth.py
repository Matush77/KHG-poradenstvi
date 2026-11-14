"""One-time Google sign-in for the local booking demo (lab only).

Opens nothing by itself: prints the Google consent URL, waits on http://127.0.0.1:8811 for the redirect,
and stores the refresh token in ~/.config/khg/google-token.json (chmod 600, outside the repo).

Scopes, kept minimal:
  calendar.app.created  create the demo's own calendar and manage events only in calendars it created
  calendar.freebusy     read busy/free times of the owner's calendars (no titles, no details)
"""
import base64, hashlib, http.server, json, os, secrets, sys, urllib.parse, urllib.request

CFG = os.path.expanduser('~/.config/khg')
env = dict(l.strip().split('=', 1) for l in open(os.path.join(CFG, 'google-oauth.env')) if '=' in l and not l.startswith('#'))
CLIENT_ID, CLIENT_SECRET = env['GOOGLE_CLIENT_ID'], env['GOOGLE_CLIENT_SECRET']
PORT = 8811
REDIRECT = 'http://127.0.0.1:%d/' % PORT
SCOPES = 'https://www.googleapis.com/auth/calendar.app.created https://www.googleapis.com/auth/calendar.freebusy'

verifier = secrets.token_urlsafe(64)
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode()
state = secrets.token_urlsafe(16)
url = 'https://accounts.google.com/o/oauth2/v2/auth?' + urllib.parse.urlencode({
    'client_id': CLIENT_ID, 'redirect_uri': REDIRECT, 'response_type': 'code', 'scope': SCOPES,
    'access_type': 'offline', 'prompt': 'consent', 'state': state,
    'code_challenge': challenge, 'code_challenge_method': 'S256', 'hl': 'cs'})
print('AUTH_URL ' + url, flush=True)


class H(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        q = urllib.parse.parse_qs(urllib.parse.urlparse(self.path).query)
        if q.get('state', [''])[0] != state or 'code' not in q:
            self.send_response(400); self.end_headers()
            self.wfile.write(('Chyba: ' + q.get('error', ['neplatná odpověď'])[0]).encode()); return
        body = urllib.parse.urlencode({'code': q['code'][0], 'client_id': CLIENT_ID, 'client_secret': CLIENT_SECRET,
                                       'redirect_uri': REDIRECT, 'grant_type': 'authorization_code', 'code_verifier': verifier}).encode()
        tok = json.load(urllib.request.urlopen('https://oauth2.googleapis.com/token', body, timeout=20))
        path = os.path.join(CFG, 'google-token.json')
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, 'w') as f:
            json.dump({'refresh_token': tok.get('refresh_token'), 'scope': tok.get('scope')}, f)
        self.send_response(200); self.send_header('Content-Type', 'text/html; charset=utf-8'); self.end_headers()
        self.wfile.write('<p style="font:18px system-ui;padding:24px">Hotovo, přístup ke kalendáři je uložený. Okno můžeš zavřít.</p>'.encode())
        print('SAVED scopes=' + str(tok.get('scope')) + ' refresh=' + str(bool(tok.get('refresh_token'))), flush=True)
        sys.exit(0) if False else setattr(self.server, 'done', True)

    def log_message(self, *a):
        pass


srv = http.server.HTTPServer(('127.0.0.1', PORT), H)
srv.done = False
while not srv.done:
    srv.handle_request()
