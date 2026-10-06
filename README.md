# KHG Poradenství

Production website for a psychotherapy and counselling practice in the Czech Republic.

**Live site:** [khgporadenstvi.cz](https://khgporadenstvi.cz)

The site is in Czech and built with plain HTML, CSS and JavaScript: no framework, no build step, no runtime dependencies. I designed, built and maintain it for a real client, and it is in active use.

## What's in it

- **Homepage** with services, pricing, credentials, an FAQ accordion and an office photo gallery with a lightbox
- **Blog** with 10 long-form articles and category filtering
- **Four self-assessment quizzes** (overthinking, procrastination, healthy boundaries, toxic relationships). Scoring runs entirely in the browser, so answers never leave the visitor's device. In the toxic-relationship quiz, high-risk results also show emergency and crisis-line contacts.
- **Booking modal** that lets visitors choose between online and in-person sessions
- **Privacy page and cookie notice.** The site uses no analytics or tracking, and the notice script also removes old Google cookies left over from a previous version of the site.

## Technical highlights

**Shared header and footer without a build step.** A static site has no templating, so the shared parts live in `partials/`. Two PowerShell scripts (`scripts/sync-header.ps1` and `scripts/sync-footer.ps1`) insert them into every page. Each script has a `-Check` mode that reports any page that is out of sync. Pages stay fully static and load no extra requests at runtime. See [`docs/FOOTER-WORKFLOW.md`](docs/FOOTER-WORKFLOW.md).

**SEO and structured data.** Every content page has its own meta description, canonical URL and Open Graph tags. The homepage has JSON-LD markup (`LocalBusiness`, `Person`, `Service`, `OfferCatalog`, `FAQPage`), and the site has a hand-maintained `sitemap.xml` and `robots.txt`.

**Performance.**
- Fonts (Inter and Playfair Display) are self-hosted as variable WOFF2 files.
- Images are WebP, served at 640 px or 1200 px depending on screen size.
- Images below the fold load lazily.
- No third-party scripts.

**Accessibility.**
- Semantic landmarks
- Keyboard-operable menu, accordion and lightbox, with correct `aria-expanded` and `aria-hidden` states
- Animations turn off for visitors who prefer reduced motion

**Hosting.** Runs on Apache shared hosting (WEBKITTY.eu). `.htaccess` redirects `www` to the bare domain and serves a custom 404 page while keeping the 404 status code.

## Project structure

```
├── index.html, blog.html, cenik.html, kviz.html, …   pages (deployed)
├── blog-*.html, quiz-*.html                          articles and quizzes
├── css/  js/  fonts/  images/                        assets (deployed)
├── partials/                                         shared header, footer, booking modal
├── scripts/                                          header/footer sync scripts (PowerShell)
└── docs/                                             developer notes
```

`partials/`, `scripts/` and `docs/` are for development only and are not uploaded to the server.

## Running locally

Any static file server works. For example:

```bash
python -m http.server 8000
```

## What I'd do differently

The sync scripts work well at about 20 pages, but they reimplement what a static site generator such as Eleventy or Astro does out of the box. If the blog keeps growing, I'd move to one so that articles can be written in Markdown and the sitemap is generated automatically.

## License and content

The source code is published as a portfolio sample. All article text, photographs, logos and branding belong to KHG Poradenství (Mgr. Kristýna Hriciková Glattová) and are shown here with permission. All rights reserved; please do not reuse the content.

Built by [@Matush77](https://github.com/Matush77).
