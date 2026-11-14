# Shipping the redesign

**Status, 13 Nov 2026:** the port is done on the branch `redesign-ship` (from `main`, not merged).
It passes the gate below and was checked in the browser at 375 px and desktop: all 20 pages load
with no 404s or console errors; on the homepage, an article and a quiz CLS stays 0, the LCP element
is unchanged and each page makes one request fewer. To ship: merge `redesign-ship` into `main`,
push, deploy, then follow section 3.

Two choices made during the port:
- The theme CSS stays inline in each page, as in the lab where it was measured, instead of a new
  `css/` file. A linked file would add a render-blocking request in `<head>`. Changing the theme
  later means updating that block in every page, like the header and footer.
- `ochrana-osobnich-udaju.html` and `404.html` were never in the lab; they got the same layers, plus
  a few lines of their own (privacy sections as hairline rows, 404 label and buttons in the theme).

The redesign lives in `ui-lab/proposal/` on the `redesign` branch. Those pages are lab copies:
they sit at `/ui-lab/proposal/…`, and `build.py` gives each one two lines that keep it out of
Google and make its links work from that folder:

```html
<base href="/">
<meta name="robots" content="noindex">
```

If either line, or any `ui-lab/` path, reaches the real pages on `main`, the live site drops out
of Google. **The site must stay indexed through the switch.** Nothing below is optional.

## 1. Gate: run before every push to `main`

```bash
python3 scripts/check-indexing.py
```

It must end with `PASS: all pages indexable`. For every URL in `sitemap.xml` it checks:

- no `noindex` / `nofollow` robots meta;
- no `<base>` tag;
- no `ui-lab` path anywhere in the page, and none in `css/` or `js/`;
- exactly one canonical, equal to the page's own sitemap URL;
- `robots.txt` does not block the site, `.htaccess` sends no `X-Robots-Tag`;
- `ui-lab/` is not tracked on `main` (it is tracked on `redesign`, which is why that branch is
  never merged as is).

Run on today's lab pages copied over the real files, it reports 55 failures, so it does catch
a careless copy.

## 2. How to port (not merge)

1. Branch from `main`. Do not merge `redesign`.
2. Write each redesigned page over the real file at its real path (`index.html`, `cenik.html`,
   `blog.html`, `kviz.html`, the 10 `blog-*.html`, the 4 `quiz-*.html`), **without** the two lab
   lines above.
3. Move the assets out of the lab and fix every path:
   - `ui-lab/proposal/fonts/Fraunces-500-Soft-cs.woff2` → `fonts/Fraunces/` (add the OFL licence);
   - `ui-lab/proposal/icons/heart.svg`, `heart-32.png`, `heart-96.png`, `heart-180.png` → `images/`;
   - `ui-lab/proposal/styles.css` and the inlined `proposal.css` layers → `css/`;
   - `ui-lab/proposal/js/quiz-*.js` → `js/`.
4. Lab links (`/ui-lab/proposal/#sluzby`, `/ui-lab/proposal/blog.html`, …) go back to the real
   ones (`/#sluzby`, `/blog.html`, …).
5. Header, footer and booking window: change `partials/` and apply the same change to every page,
   including `ochrana-osobnich-udaju.html` and `404.html` (see `FOOTER-WORKFLOW.md`).
6. Bump `?v=` on every CSS and JS file that changed, in every page that loads it.
7. Keep titles, meta descriptions, H1s, canonicals, JSON-LD and URLs exactly as they are.
   The SEO review of 12 Nov found none of them changed; keep it that way.
8. Run the gate (section 1) until it passes. Then check the rules in `.claude/CLAUDE.md`:
   both widths, CLS, LCP, request count, console and 404s.

## 3. Right after the deploy

```bash
python3 scripts/check-indexing.py --live
```

Same checks on `https://khgporadenstvi.cz`, plus: every page answers HTTP 200, no
`X-Robots-Tag: noindex` header, and `/ui-lab/proposal/` answers 404 (the lab is not on the
server). If anything fails, revert the deploy first and investigate second.

Then:

- Search Console → URL inspection on the homepage and one article: "URL is on Google" and
  "Indexing allowed: Yes". Request indexing for the homepage.
- PageSpeed Insights (mobile) on the homepage and one article: report score, LCP, CLS, TBT.
- One to two weeks later: Search Console → Pages. The indexed count must not drop.

## 4. Own booking (ships with the redesign, owner's choice 14 Nov)

The "Objednat se" window now opens `/rezervace.html` (format from the window, `?forma=osobne`)
instead of Google's booking pages. Ported from the lab (`ui-lab/rezervace-ukazka.html` with the
owner's picks, `ui-lab/booking-php/`):

- `rezervace.html`: calendar, details, then Stripe Checkout (redirect); in the sitemap.
- `rezervace/zruseni.html` (client's move or cancel link), `rezervace/sprava.html` (owner's link
  from "Nová rezervace"), `rezervace/hodiny.html` (her hours, shared password): all `noindex`.
- `booking/`: the PHP back end. `.htaccess` sends `/api/…` to `booking/api.php` and answers 404
  for anything under `/booking/`. Tests: `php booking/tests/run.php`. Local run:
  `php -S 127.0.0.1:8830 -t . booking/router.php`.

Before this goes live, on the hosting (none of it is in Git):

- PHP 8.4 with curl, openssl, pdo_sqlite; config in `~/.config/khg/` outside `public_html`
  (`booking.env` with `BOOKING_MODE=live` and `SITE_URL=https://khgporadenstvi.cz`, `booking-secret`,
  Stripe live keys and webhook secret, Google OAuth token, `smtp.env`), files chmod 600.
- `php booking/tools/owner-password.php` for the hours page password; her weekly hours entered there.
- Stripe webhook to `https://khgporadenstvi.cz/api/webhook`; Scheduled Task every 5 minutes:
  `php -f …/booking/cron.php`.
- Check after deploy: `/booking/api.php` and `/booking/src/config.php` answer 404, `/api/slots` answers JSON.
