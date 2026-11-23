"""Screenshots of every page in every state, for the visual proof (run by proof.py; see docs/VISUAL-PROOF.md).

python3 shoot.py <run name> <port> [page,page]  -> shots/<run name>/<page>/<state>@<width>.png + styles.json

Each page: loaded, scrolled to the bottom and back (fade-ins and lazy images done), then screenshots at
20 widths; plus menu, homepage FAQ / reviews / lightbox, quiz screens, booking steps, and every element
forced into :hover and into :focus-visible (CDP). Transitions and animations are frozen, every scenario starts
with an empty cache, and images are waited for after each resize, so two runs of the same files give the same
pixels (apart from rare one-pixel flicker; proof.py shoots each side twice for that)."""
import json, os, sys, pathlib, hashlib
from multiprocessing import Pool
from playwright.sync_api import sync_playwright

HERE = pathlib.Path(__file__).parent
ROOT = HERE.parent.parent  # the site
SHOTS = pathlib.Path(os.environ.get('SHOTS_DIR', HERE / 'shots'))
W = [320, 372, 388, 420, 500, 530, 570, 620, 670, 710, 740, 790, 840, 880, 930, 963, 985, 1010, 1060, 1280]
NARROW = [w for w in W if w < 969]
PAGES = sorted(p.name for p in ROOT.glob('*.html')) + ['rezervace/zruseni.html']
NONVISUAL = ['transition', 'animation', 'cursor', 'pointer-events', 'user-select', 'touch-action',
             'scroll-behavior', 'scroll-margin-top', 'will-change', 'outline', 'outline-offset', 'z-index']

SETTLE = """async () => {
  try { await document.fonts.ready; } catch (e) {}
  for (let i = 0; i < 50 && document.querySelector('link[rel=preload][as=style]'); i++) await new Promise(r => setTimeout(r, 100));
  const h = document.documentElement.scrollHeight;
  for (let y = 0; y < h; y += 400) { scrollTo(0, y); await new Promise(r => setTimeout(r, 40)); }
  scrollTo(0, document.documentElement.scrollHeight); await new Promise(r => setTimeout(r, 300));
  scrollTo(0, 0);
  // hidden lazy images (lightbox, closed panels) never load: wait for the rest, at most 4 s
  await Promise.race([Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.addEventListener('load', r); i.addEventListener('error', r); }))), new Promise(r => setTimeout(r, 4000))]);
  await new Promise(r => setTimeout(r, 400));
}"""
IMGS = """async () => {
  document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; });
  const all = [...document.images].filter(i => i.getClientRects().length);
  await Promise.race([Promise.all(all.map(i => i.decode().catch(() => 0))), new Promise(r => setTimeout(r, 5000))]);
  if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur();
  await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));
}"""
STYLES = """props => [...document.querySelectorAll('*')].map(el => {
  const c = getComputedStyle(el); return el.tagName + '.' + el.className + ' ' + props.map(p => c.getPropertyValue(p)).join('|'); })"""


class Shooter:
    def __init__(self, pw, side, port, page_name):
        self.out = SHOTS / side / page_name.replace('/', '_')
        self.out.mkdir(parents=True, exist_ok=True)
        self.base = f'http://127.0.0.1:{port}/'
        self.name = page_name
        self.browser = pw.chromium.launch()
        self.errors = []
        self.ctx = None

    def fresh(self):
        # new context per scenario: empty cache, so srcset picks are the same every run
        if self.ctx:
            self.ctx.close()
        self.ctx = self.browser.new_context(viewport={'width': 375, 'height': 800}, reduced_motion='no-preference',
                                            timezone_id='Europe/Prague', locale='cs-CZ')
        self.page = self.ctx.new_page()
        self.page.set_default_timeout(20000)
        self.page.on('pageerror', lambda e: self.errors.append(str(e)))

    def load(self, q='', keep=False):
        if not keep:
            self.fresh()
        self.page.set_viewport_size({'width': 375, 'height': 800})
        self.page.goto(self.base + self.name + q, wait_until='networkidle')
        self.page.evaluate(SETTLE)
        self.still()

    def still(self):
        # settled end states only: no transitions or animations in flight, then two quiet frames
        self.page.add_style_tag(content='*,*::before,*::after{transition:none!important;animation:none!important;scroll-behavior:auto!important}html{overflow-anchor:none!important}')
        self.page.evaluate('new Promise(r => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(r, 150))))')

    def shot(self, state, widths, full=True):
        for w in widths:
            self.page.set_viewport_size({'width': w, 'height': 800})
            self.page.wait_for_timeout(80)
            self.page.evaluate(IMGS)  # srcset may swap to a sharper file after a resize
            if full:  # fixed header and skip link are drawn at the scroll position: make it exactly 0
                self.page.evaluate('new Promise(r => { scrollTo(0, 0); requestAnimationFrame(() => requestAnimationFrame(r)); })')
            self.page.screenshot(path=self.out / f'{state}@{w}.png', full_page=full, animations='disabled',
                                 caret='hide', scale='css')

    def click(self, sel):
        el = self.page.query_selector(sel)
        if el:
            el.click()
            return True
        return False

    def force(self, states):
        cdp = self.ctx.new_cdp_session(self.page)
        cdp.send('DOM.enable'); cdp.send('CSS.enable')
        root = cdp.send('DOM.getDocument', {'depth': -1})['root']['nodeId']
        for n in cdp.send('DOM.querySelectorAll', {'nodeId': root, 'selector': '*'})['nodeIds']:
            cdp.send('CSS.forcePseudoState', {'nodeId': n, 'forcedPseudoClasses': states})
        return cdp

    def run(self):
        styles = {}
        self.fresh()
        self.page.goto(self.base + self.name, wait_until='networkidle'); self.page.evaluate(SETTLE)
        for w in (375, 1280):
            self.page.set_viewport_size({'width': w, 'height': 800}); self.page.wait_for_timeout(80)
            styles[w] = self.page.evaluate(STYLES, NONVISUAL)
        self.still()
        self.shot('page', W)
        for st, pseudo in (('hover', ['hover']), ('focus', ['focus', 'focus-visible', 'focus-within'])):
            self.load()
            cdp = self.force(pseudo)
            self.page.wait_for_timeout(200)
            self.shot(st, [375, 1280])
            cdp.detach()
        self.load()
        if self.click('.nav-toggle'):
            self.page.wait_for_timeout(500)
            self.shot('menu', NARROW, full=False)
        if self.name == 'index.html':
            self.load()
            if self.click('.faq-question'):
                self.page.wait_for_timeout(500); self.shot('faq', W)
            if self.click('.rv-more'):
                self.page.wait_for_timeout(500); self.shot('reviews-more', W)
            if self.click('.office-gallery a, .office-gallery button, .office-gallery img'):
                self.page.wait_for_timeout(700); self.shot('lightbox', W, full=False)
        if self.name.startswith('quiz-'):
            for pick in ('first', 'last'):
                self.load(); self.page.evaluate('localStorage.clear()'); self.load(keep=True)
                if not self.click('#startQuizBtn'):
                    break
                self.page.wait_for_timeout(500)
                if pick == 'first':
                    self.shot('q1', W)
                for q in range(40):
                    if self.page.evaluate("(() => { const r = document.getElementById('resultsSection'); return !!(r && r.offsetParent); })()"):
                        break
                    opts = self.page.query_selector_all('#optionsContainer .quiz-option')
                    if not opts:
                        break
                    (opts[0] if pick == 'first' else opts[-1]).click()
                    self.page.wait_for_timeout(250)
                    if q == 0 and pick == 'first':
                        self.shot('q1-selected', W)
                    if q == 1 and pick == 'last':
                        self.shot('q2-selected', W)
                    nxt = self.page.query_selector('#nextBtn')
                    if nxt and nxt.is_enabled():
                        nxt.click(); self.page.wait_for_timeout(350)
                    else:
                        break
                self.page.wait_for_timeout(600); self.page.evaluate(SETTLE); self.still()
                self.shot('results-' + pick, W)
        if self.name == 'rezervace.html':
            for q, tag in (('', ''), ('?forma=osobne', '-osobne')):
                self.load(q)
                # the spinner stops when the free times arrived, or when the API answered with an error (CI: no Google)
                self.page.wait_for_function("!document.querySelector('.rez .spin, .rez .grid.loading')", timeout=20000)
                self.page.wait_for_timeout(800)
                self.shot('cal' + tag, W)
                if self.click('.day.free'):
                    self.page.wait_for_timeout(500); self.shot('day' + tag, W)
                if self.click('.time'):
                    self.page.wait_for_timeout(700); self.shot('form' + tag, W)
                if self.click('#go'):
                    self.page.wait_for_timeout(500); self.shot('form-errors' + tag, W)
        (self.out / 'styles.json').write_text(json.dumps({'styles': styles, 'errors': self.errors}))
        self.browser.close()


def job(args):
    side, port, name = args
    try:
        with sync_playwright() as pw:
            Shooter(pw, side, port, name).run()
        return name, side, 'ok'
    except Exception as e:
        return name, side, 'ERROR ' + str(e)[:300]


if __name__ == '__main__':
    name, port = sys.argv[1], int(sys.argv[2])
    pages = sys.argv[3].split(',') if len(sys.argv) > 3 else PAGES
    jobs = [(name, port, n) for n in pages]
    # heavy pages first so the pool ends together
    jobs.sort(key=lambda j: -(5 if j[2].startswith(('quiz-', 'index', 'rezervace.html')) else 1))
    bad = 0
    with Pool(int(os.environ.get('WORKERS', os.cpu_count() or 4))) as pool:
        for r in pool.imap_unordered(job, jobs):
            if r[2] != 'ok':
                bad += 1
                print(*r, flush=True)
    sys.exit(1 if bad else 0)
