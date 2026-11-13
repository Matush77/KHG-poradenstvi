# Shipping the redesign

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
   - `ui-lab/proposal/img/portrait-256.webp`, `portrait-480.webp`, `portrait-avatar-192.webp` → `images/`
     (O mně and the article author boxes);
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
