"""Local booking demo with real Stripe TEST payments (lab only, never deployed).

Serves the repo like `python3 -m http.server` and adds a tiny API that talks to Stripe in test mode:
  GET  /api/config                 publishable key for Stripe.js
  POST /api/checkout               Checkout Session, mode "redirect" (variant 0) or "embedded" (variant 3)
  GET  /api/checkout?id=cs_...     status of a Checkout Session (after the return)
  POST /api/intent                 PaymentIntent for the Payment Element in our design (variant 2)
  GET  /api/intent?id=pi_...       status of a PaymentIntent (after a bank redirect)

Keys come from ~/.config/khg/stripe-test.env and must be test keys; the secret one never leaves this process.
The price is fixed here (1 100 Kc), never taken from the browser. Binds to 127.0.0.1 only.
Run: python3 ui-lab/stripe-demo/server.py [port]   (default 8810)
"""
import base64, datetime, email.message, hashlib, hmac, http.server, json, os, secrets, sys, threading, urllib.error, urllib.parse, urllib.request
from zoneinfo import ZoneInfo

PRAGUE = ZoneInfo('Europe/Prague')


def session_start(meta):
    """The session's start as Prague time (summer time included), from the payment's metadata."""
    h, m = (int(x) for x in meta['cas'].split(':'))
    return datetime.datetime.fromisoformat(meta['den']).replace(hour=h, minute=m, tzinfo=PRAGUE)
try:
    import gcal  # Google Calendar (optional): after payment, the session goes into the test calendar
except Exception as e:  # no Google token yet
    gcal = None
    print('Google Calendar off:', e, flush=True)

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
ENV = os.path.expanduser('~/.config/khg/stripe-test.env')
PRICE = 110000  # 1 100 Kc in halere, Stripe counts CZK with two decimals
DAYS = ['pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle']
MONTHS = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince']
FORMATS = {'online': 'Sezení online', 'osobne': 'Sezení osobně'}


def load_keys():
    keys = {}
    for line in open(ENV):
        line = line.strip()
        if line and not line.startswith('#') and '=' in line:
            k, v = line.split('=', 1)
            keys[k] = v
    sk, pk = keys.get('STRIPE_SECRET_KEY', ''), keys.get('STRIPE_PUBLISHABLE_KEY', '')
    if not (sk.startswith(('sk_test_', 'rk_test_')) and pk.startswith('pk_test_')):
        sys.exit('Refusing to start: the keys in %s are not Stripe TEST keys.' % ENV)
    return sk, pk


SK, PK = load_keys()
BOOKED = os.path.join(os.path.expanduser('~/.config/khg'), 'demo-bookings.json')  # payment id -> calendar event, so a reload never books twice
_lock = threading.Lock()
PLACE = 'Vítkovická 3299/3A, Ostrava'
SITE = 'http://localhost:8810'  # real back end: https://khgporadenstvi.cz
CANCEL_PAGE = '/ui-lab/rezervace/zruseni.html'
OUTBOX = os.path.expanduser('~/.config/khg/outbox')  # demo: e-mails are written here instead of being sent
import mail


def _secret():
    """Key for signing cancel links; created once, kept outside the repo."""
    path = os.path.expanduser('~/.config/khg/booking-secret')
    if not os.path.exists(path):
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'w') as f:
            f.write(secrets.token_hex(32))
    return open(path).read().strip().encode()


SECRET = _secret()


def cancel_token(pay_id):
    """Only whoever has the e-mail can cancel: HMAC of the payment id, never guessable from the id alone."""
    return base64.urlsafe_b64encode(hmac.new(SECRET, pay_id.encode(), hashlib.sha256).digest()[:18]).decode()


def token_ok(pay_id, token):
    return bool(pay_id) and hmac.compare_digest(cancel_token(pay_id), str(token or ''))


def cancel_url(pay_id):
    return SITE + CANCEL_PAGE + '?' + urllib.parse.urlencode({'b': pay_id, 't': cancel_token(pay_id)})


def deadline(start):
    return start - datetime.timedelta(hours=FREE_CANCEL_HOURS)


def write_mail(pay_id, meta, meet):
    """Demo: the confirmation e-mail as an .eml file in the outbox (real back end: sent through SMTP)."""
    start = session_start(meta)
    m = mail.build(meta, start, deadline(start), cancel_url(pay_id), meet, pay_id)
    msg = email.message.EmailMessage()
    msg['Subject'], msg['From'] = m['subject'], '%s <rezervace@khgporadenstvi.cz>' % mail.SENDER
    msg['To'], msg['Reply-To'] = meta.get('email') or 'neznamy@example.com', mail.MAIL
    msg.set_content(m['text'])
    msg.add_alternative(m['html'], subtype='html')
    msg.add_attachment(m['ics'], subtype='calendar', charset='utf-8', filename='sezeni.ics')
    os.makedirs(OUTBOX, mode=0o700, exist_ok=True)
    path = os.path.join(OUTBOX, pay_id + '.eml')
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, 'wb') as f:
        f.write(bytes(msg))
    return m


def book_after_payment(pay_id, meta):
    """Runs once per paid payment: puts the session into the demo's test calendar (Meet link for online).
    In the real back end this runs from Stripe's webhook, not from the return page."""
    if not gcal or not meta.get('den'):
        return {}
    with _lock:
        done = json.load(open(BOOKED)) if os.path.exists(BOOKED) else {}
        if pay_id in done:
            return done[pay_id]
        start = session_start(meta)
        end = start + datetime.timedelta(minutes=50)
        online = meta.get('forma') != 'osobne'
        busy, _ = gcal.busy(start.isoformat(), end.isoformat(), ('primary', gcal.test_calendar()))
        clash = any(busy.values())
        contact = meta.get('email') if online else meta.get('telefon')
        ev = gcal.create_session(start.replace(tzinfo=None).isoformat(), end.replace(tzinfo=None).isoformat(),
                                 'TEST · ' + ('Online' if online else 'Osobně') + ' · ' + (meta.get('jmeno') or 'klient'),
                                 'Rezervace z webu (demo, Stripe test). Kontakt: %s. Platba: %s' % (contact or '-', pay_id),
                                 online, None if online else PLACE, meta.get('email') if online else None)
        rec = {'event': ev['id'], 'meet': ev.get('hangoutLink'), 'clash': clash}
        if meta.get('email'):  # in person without an e-mail: the real back end sends an SMS instead (open)
            write_mail(pay_id, meta, rec['meet'])
            rec['mail'] = True
        done[pay_id] = rec
        fd = os.open(BOOKED, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, 'w') as f:
            json.dump(done, f)
        print('BOOKED', pay_id[:14], rec, flush=True)
        return rec


def flatten(data, prefix=''):
    """Stripe wants form fields like line_items[0][price_data][currency]."""
    out = []
    items = data.items() if isinstance(data, dict) else enumerate(data)
    for k, v in items:
        name = '%s[%s]' % (prefix, k) if prefix else str(k)
        if isinstance(v, (dict, list)):
            out += flatten(v, name)
        elif v is not None:
            out.append((name, 'true' if v is True else 'false' if v is False else str(v)))
    return out


def stripe(method, path, data=None):
    body = urllib.parse.urlencode(flatten(data)).encode() if data else None
    req = urllib.request.Request('https://api.stripe.com/v1/' + path, data=body, method=method, headers={
        'Authorization': 'Basic ' + base64.b64encode((SK + ':').encode()).decode(),
        'Content-Type': 'application/x-www-form-urlencoded'})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return 200, json.load(r)
    except urllib.error.HTTPError as e:
        return e.code, json.load(e)


def booking(b):
    """Checks the choice from the browser and builds the Czech line-item text."""
    fmt = b.get('f') if b.get('f') in FORMATS else 'online'
    d = datetime.date.fromisoformat(str(b.get('date', ''))[:10])
    h, m = (int(x) for x in str(b.get('time', '')).split(':')[:2])
    end = h * 60 + m + 50
    label = '%s · %s %d. %s · %d:%02d–%d:%02d' % (FORMATS[fmt], DAYS[d.weekday()], d.day, MONTHS[d.month - 1], h, m, end // 60, end % 60)
    email = str(b.get('email', ''))[:200] or None
    meta = {'forma': fmt, 'den': d.isoformat(), 'cas': '%d:%02d' % (h, m), 'jmeno': str(b.get('name', ''))[:100],
            'email': email or '', 'telefon': str(b.get('phone', ''))[:40]}
    return label, email, meta


FREE_CANCEL_HOURS = 36


def cancel_booking(pay_id, now=None):
    """Client cancels (in the real system: the link in the confirmation e-mail).
    36+ hours before: full refund through Stripe. Later: no refund. Always: the calendar event goes, the slot frees up."""
    with _lock:
        done = json.load(open(BOOKED)) if os.path.exists(BOOKED) else {}
        rec = done.get(pay_id)
        if not rec:
            return 404, {'error': 'Rezervace nenalezena.'}
        if rec.get('cancelled'):
            return 200, rec
        code, sess = stripe('GET', 'checkout/sessions/' + pay_id)
        if code != 200:
            return code, {'error': sess.get('error', {}).get('message')}
        m = sess.get('metadata', {})
        start = session_start(m)
        now = now or datetime.datetime.now(datetime.timezone.utc)
        hours = (start - now).total_seconds() / 3600
        if hours <= 0:
            return 400, {'error': 'Sezení už začalo nebo proběhlo, zrušit ho nejde.'}
        refund = None
        if hours >= FREE_CANCEL_HOURS:
            # idempotency key: a double click never refunds twice
            body = urllib.parse.urlencode({'payment_intent': sess['payment_intent'], 'reason': 'requested_by_customer',
                                           'metadata[duvod]': 'zruseni klientem %.0f h predem' % hours}).encode()
            req = urllib.request.Request('https://api.stripe.com/v1/refunds', data=body, method='POST', headers={
                'Authorization': 'Basic ' + base64.b64encode((SK + ':').encode()).decode(), 'Idempotency-Key': 'cancel-' + pay_id})
            try:
                with urllib.request.urlopen(req, timeout=20) as r:
                    rf = json.load(r)
            except urllib.error.HTTPError as e:
                return e.code, {'error': json.load(e).get('error', {}).get('message')}
            refund = {'id': rf['id'], 'status': rf['status'], 'amount': rf['amount']}
        if gcal and rec.get('event'):
            try:
                gcal.delete_session(rec['event'])
            except RuntimeError as e:
                if '410' not in str(e) and '404' not in str(e):
                    raise
        rec.update(cancelled=True, refund=refund, hours_before=round(hours, 1))
        done[pay_id] = rec
        fd = os.open(BOOKED, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
        with os.fdopen(fd, 'w') as f:
            json.dump(done, f)
        print('CANCELLED', pay_id[:14], rec, flush=True)
        return 200, rec


class Handler(http.server.SimpleHTTPRequestHandler):
    def __init__(self, *a, **kw):
        super().__init__(*a, directory=ROOT, **kw)

    def end_headers(self):
        self.send_header('Cache-Control', 'no-store')
        super().end_headers()

    def send_json(self, code, obj):
        body = json.dumps(obj).encode()
        self.send_response(code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        u = urllib.parse.urlparse(self.path)
        q = urllib.parse.parse_qs(u.query)
        if u.path == '/api/config':
            return self.send_json(200, {'pk': PK})
        if u.path == '/api/checkout' and q.get('id', [''])[0].startswith('cs_'):
            code, s = stripe('GET', 'checkout/sessions/' + q['id'][0])
            paid = s.get('payment_status') == 'paid'
            cal = book_after_payment(s['id'], s.get('metadata', {})) if paid else {}
            return self.send_json(code, {'status': s.get('status'), 'paid': paid, 'meta': s.get('metadata', {}),
                                         'meet': cal.get('meet'), 'id': s.get('id'), 'cancelled': cal.get('cancelled'),
                                         'cancel': cancel_url(s['id'])[len(SITE):] if paid else None, 'error': s.get('error', {}).get('message')})
        if u.path in ('/api/booking', '/api/email'):
            pid, tok = q.get('b', [''])[0], q.get('t', [''])[0]
            if not token_ok(pid, tok):
                return self.send_json(403, {'error': 'Odkaz není platný. Zkontrolujte, že jste ho zkopírovali celý.'})
            code, s = stripe('GET', 'checkout/sessions/' + pid)
            if code != 200 or s.get('payment_status') != 'paid':
                return self.send_json(404, {'error': 'Rezervace nenalezena.'})
            meta = s.get('metadata', {})
            done = json.load(open(BOOKED)) if os.path.exists(BOOKED) else {}
            rec = done.get(pid, {})
            start = session_start(meta)
            if u.path == '/api/email':  # demo preview of the confirmation e-mail
                m = mail.build(meta, start, deadline(start), cancel_url(pid), rec.get('meet'), pid)
                part = q.get('part', ['html'])[0]
                body = (m['ics'] if part == 'ics' else m['text'] if part == 'txt' else m['html']).encode()
                ctype = {'ics': 'text/calendar', 'txt': 'text/plain'}.get(part, 'text/html')
                self.send_response(200); self.send_header('Content-Type', ctype + '; charset=utf-8')
                self.send_header('Cache-Control', 'no-store'); self.send_header('Content-Length', str(len(body))); self.end_headers()
                return self.wfile.write(body)
            now = datetime.datetime.now(datetime.timezone.utc)
            return self.send_json(200, {'forma': meta.get('forma'), 'den': meta.get('den'), 'cas': meta.get('cas'),
                                        'when': mail.at(start), 'deadline': mail.until(deadline(start)),
                                        'started': now >= start, 'refund': now <= deadline(start),
                                        'cancelled': bool(rec.get('cancelled')), 'refunded': bool(rec.get('refund'))})
        if u.path == '/api/intent' and q.get('id', [''])[0].startswith('pi_'):
            code, p = stripe('GET', 'payment_intents/' + q['id'][0])
            return self.send_json(code, {'status': p.get('status'), 'paid': p.get('status') == 'succeeded',
                                         'meta': p.get('metadata', {}), 'error': p.get('error', {}).get('message')})
        if u.path.startswith('/api/'):
            return self.send_json(404, {'error': 'not found'})
        return super().do_GET()

    def do_POST(self):
        u = urllib.parse.urlparse(self.path)
        if u.path == '/api/cancel':
            b = json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0)) or 0) or b'{}')
            if not token_ok(str(b.get('b', '')), b.get('t')):
                return self.send_json(403, {'error': 'Odkaz není platný.'})
            code, res = cancel_booking(str(b['b']))
            return self.send_json(code, res)
        try:
            b = json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0)) or 0) or b'{}')
            label, email, meta = booking(b)
        except (ValueError, TypeError) as e:
            return self.send_json(400, {'error': 'Neplatný termín: %s' % e})
        base = 'http://%s/ui-lab/rezervace-ukazka.html' % self.headers.get('Host', '127.0.0.1:8810')
        back = urllib.parse.urlencode({'stripe': 1, 'pay': b.get('pay', 0), 'forma': meta['forma'], 'den': meta['den'], 'cas': meta['cas'], 'krok': 2})
        if u.path == '/api/checkout':
            embedded = b.get('mode') == 'embedded'
            data = {'mode': 'payment', 'locale': 'cs', 'customer_email': email, 'metadata': meta,
                    'payment_intent_data': {'description': label, 'metadata': meta},
                    'line_items': [{'quantity': 1, 'price_data': {'currency': 'czk', 'unit_amount': PRICE, 'product_data': {'name': label}}}]}
            ret = base + '?' + back.replace('krok=2', 'paid={CHECKOUT_SESSION_ID}')
            if embedded:
                data.update({'ui_mode': 'embedded', 'return_url': ret})
            else:
                data.update({'success_url': ret, 'cancel_url': base + '?' + back})
            code, s = stripe('POST', 'checkout/sessions', data)
            if code != 200:
                return self.send_json(code, {'error': s.get('error', {}).get('message')})
            return self.send_json(200, {'url': s.get('url'), 'clientSecret': s.get('client_secret')})
        if u.path == '/api/intent':
            code, p = stripe('POST', 'payment_intents', {'amount': PRICE, 'currency': 'czk', 'description': label,
                                                         'receipt_email': email, 'metadata': meta,
                                                         'automatic_payment_methods': {'enabled': True}})
            if code != 200:
                return self.send_json(code, {'error': p.get('error', {}).get('message')})
            return self.send_json(200, {'clientSecret': p.get('client_secret'), 'returnUrl': base + '?' + back.replace('krok=2', 'pi=1')})
        return self.send_json(404, {'error': 'not found'})


def sweep():
    """Safety net: a client who paid but closed the tab never reaches the return page. Books every paid
    session of the last 24 h that is not booked yet. In the real back end this sits next to the Stripe webhook."""
    since = int(datetime.datetime.now().timestamp()) - 24 * 3600
    code, page = stripe('GET', 'checkout/sessions?status=complete&limit=100&created[gte]=%d' % since)
    if code != 200:
        print('SWEEP failed', code, flush=True)
        return []
    booked = []
    for sess in page.get('data', []):
        # never the PHP back end's sessions (metadata zdroj=web): both demos share one Stripe test account
        if sess.get('payment_status') == 'paid' and sess.get('metadata', {}).get('den') and sess['metadata'].get('zdroj') != 'web':
            done = json.load(open(BOOKED)) if os.path.exists(BOOKED) else {}
            if sess['id'] not in done:
                book_after_payment(sess['id'], sess['metadata'])
                booked.append(sess['id'])
    if booked:
        print('SWEEP booked', [b[:14] for b in booked], flush=True)
    return booked


def sweep_loop(every=120):
    while True:
        try:
            sweep()
        except Exception as e:  # never let the safety net take the server down
            print('SWEEP error', e, flush=True)
        threading.Event().wait(every)


if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8810
    print('Stripe TEST demo on http://127.0.0.1:%d/ui-lab/rezervace-ukazka.html?stripe=1' % port, flush=True)
    if gcal:
        threading.Thread(target=sweep_loop, daemon=True).start()
    http.server.ThreadingHTTPServer(('127.0.0.1', port), Handler).serve_forever()
