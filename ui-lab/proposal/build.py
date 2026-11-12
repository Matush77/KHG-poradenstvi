"""Build ui-lab/proposal/index.html: the live homepage with the 12 Nov theme proposal applied.
Run: python3 ui-lab/proposal/build.py   Open: http://localhost:8000/ui-lab/proposal/"""
import pathlib, re

here = pathlib.Path(__file__).parent
lab = here.parent
root = lab.parent
page = (root / "index.html").read_text(encoding="utf-8")
css = (here / "proposal.css").read_text(encoding="utf-8")
reviews = (lab / "reviews" / "section.html").read_text(encoding="utf-8")


def sub(old, new, count=1):
    global page
    assert old in page, f"not found: {old[:60]}"
    page = page.replace(old, new, count)


# lab page: resolve paths from site root, keep out of search
sub("<head>", '<head>\n    <base href="/">\n    <meta name="robots" content="noindex">')

# fonts: Inter only (Playfair is never requested once nothing uses it)
page = re.sub(r'\s*<link rel="preload" href="fonts/Playfair_Display/[^>]+>', "", page)

# critical CSS: drop the serif face and point the hero title at Inter, so first paint never asks for Playfair
page = re.sub(r"\s*@font-face\{font-family:'Playfair Display(?: Fallback(?: Android)?)?'[^}]*\}", "", page)
sub('.hero-title{font-family:"Playfair Display","Playfair Display Fallback","Playfair Display Fallback Android",Georgia,serif;',
    '.hero-title{font-family:Inter,"Inter Fallback","Inter Fallback Android",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;')

# heading face: Fraunces (owner, 12 Nov), self-hosted, preloaded like Inter, with size-matched fallbacks in the critical CSS
FRAUNCES_FACES = ("@font-face{font-family:'Fraunces';src:url('/ui-lab/proposal/fonts/Fraunces-500-Soft-cs.woff2') format('woff2');font-weight:400 700;font-style:normal;font-display:swap}"
    "@font-face{font-family:'Fraunces Fallback';src:local('Georgia Bold'),local('Georgia-Bold');font-weight:400 700;size-adjust:91.58%;ascent-override:106.79%;descent-override:27.84%;line-gap-override:0%}"
    "@font-face{font-family:'Fraunces Fallback Android';src:local('Noto Serif Bold'),local('NotoSerif-Bold');font-weight:400 700;size-adjust:92.44%;ascent-override:105.80%;descent-override:27.59%;line-gap-override:0%}")
FRAUNCES_PRELOAD = '<link rel="preload" href="ui-lab/proposal/fonts/Fraunces-500-Soft-cs.woff2" as="font" type="font/woff2" crossorigin>'
sub('<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>',
    '<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>\n    ' + FRAUNCES_PRELOAD)
sub(".hero-title{font-family:Inter,\"Inter Fallback\",\"Inter Fallback Android\",-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;",
    ".hero-title{font-family:Fraunces,\"Fraunces Fallback\",\"Fraunces Fallback Android\",Georgia,serif;font-weight:500!important;font-synthesis:none;letter-spacing:-.01em;")
page = page.replace("@font-face{font-family:'Inter Fallback';", FRAUNCES_FACES + "@font-face{font-family:'Inter Fallback';", 1)

# styles.css without the Playfair faces, as the real change would ship it
sheet = (root / "css" / "styles.css").read_text(encoding="utf-8")
sheet2 = re.sub(r"/\* Playfair Display Font Family \*/\s*@font-face\s*\{[^}]*\}", "", sheet)
sheet2 = re.sub(r"@font-face\s*\{\s*font-family:\s*'Playfair Display Fallback(?: Android)?';[^}]*\}", "", sheet2)
sheet2 = sheet2.replace("url('../fonts/", "url('/fonts/").replace('url("../fonts/', 'url("/fonts/').replace("url('../images/", "url('/images/").replace('url("../images/', 'url("/images/')
assert "Playfair Display';" not in sheet2.split("--font-heading")[0], "Playfair face still present"
(here / "styles.css").write_text(sheet2, encoding="utf-8")
page = page.replace("css/styles.css?v=20261110-3", "ui-lab/proposal/styles.css?v=lab1")

# proposal layer: inline, after the async stylesheets, so it wins the cascade from first paint
sub("</noscript>", "</noscript>\n    <style id=\"proposal\">\n" + css + "\n    .rv-title { font-weight: 500 !important; }\n    </style>")

# gallery: out of the "Praktické informace" card, into its own full-width block below it
m = re.search(r'\n\s*<figure class="office-gallery".*?</figure>', page, flags=re.S)
fig = m.group(0)
page = page[:m.start()] + page[m.end():]
close = "\n            </div>\n        </div>\n    </section>"
i = page.index(close, m.start())
page = page[:i] + "\n            </div>\n" + fig.replace('sizes="(max-width: 768px) 30vw, 200px"', 'sizes="(max-width: 768px) 84vw, 300px"') + "\n        </div>\n    </section>" + page[i + len(close):]

# reviews: the card row from ui-lab/reviews
page, n = re.subn(r"    <!-- Testimonials Section -->.*?</section>\n", lambda m: reviews, page, count=1, flags=re.S)
assert n == 1

# copy: lowercase formal "vy" inside sentences
page = re.sub(r"(?<=[a-zěščřžýáíéůúňťď,„(] )(Vy|Vám|Vás|Vámi|Váš|Vaše|Vašich|Vaší|Vašim|Vašimi|Vaši|Vašeho|Vaším|Vašem|Vašemu|Vašich)\b",
              lambda m: m.group(1).lower(), page)

# copy: CTA as facts instead of a slogan
sub('<p class="cta-heading">Připraveni začít?</p>', '<p class="cta-heading">První setkání</p>')
sub('<p>Objednejte si první setkání a společně najdeme cestu k vašim cílům.</p>',
    '<p>50 minut, 1 100 Kč, online nebo osobně na Vítkovické v Ostravě.</p>')

# booking window: the format leads the title, no separate label (owner, 12 Nov)
sub('<span class="booking-option-label">Online</span>\n                    <span class="booking-option-title">Terapie online</span>',
    '<span class="booking-option-title">Online</span>')
sub('<span class="booking-option-label">Ostrava</span>\n                    <span class="booking-option-title">Terapie osobně</span>',
    '<span class="booking-option-title">Osobně v Ostravě</span>')

# services: one headline instead of a label plus a headline (owner, 12 Nov)
sub('<p class="service-eyebrow">Individuální psychoterapie</p>\n                <h3>Terapie pro děti a dospělé</h3>',
    '<h3>Individuální psychoterapie pro děti a dospělé</h3>')

# Audit 13 Nov (owner approved): three parts to the theme's "tone, not boxes"
# 1. topics: a plain list under a hairline, heading without the colon
sub('<h3>Nejčastější témata, se kterými pracuji:</h3>', '<h3>Nejčastější témata, se kterými pracuji</h3>')
# 2. service: "Co nabízím" repeated the title and the facts; only what is new stays, as one line under the description
page, n = re.subn(r'\n\s*<div class="service-features-main">.*?</ul>\s*</div>', "", page, count=1, flags=re.S)
assert n == 1
sub('v bezpečném a respektujícím prostředí.\n                </p>',
    'v bezpečném a respektujícím prostředí.\n                </p>\n                <p class="service-extra">Termíny hledáme podle vašich možností. Svou práci pravidelně konzultuji v supervizi.</p>')
# 3. Praktické informace: rows as on Ceník (title + text), not bold-lead bullets in a card
def info_rows(m):
    items = re.findall(r'<li><strong>([^<:]+):</strong>\s*(.*?)</li>', m.group(1), flags=re.S)
    last = re.search(r'<p><strong>([^<:]+):</strong>\s*(.*?)</p>', m.group(1), flags=re.S)
    items.append(last.groups())
    rows = "\n".join(f'                        <div class="info-row"><h4>{t}</h4><p>{d.strip()}</p></div>' for t, d in items)
    return ('<div class="technical-info">\n                <div class="technical-info-content">\n                    <h3>Praktické informace</h3>\n'
            '                    <div class="info-rows">\n' + rows + '\n                    </div>\n                </div>\n            </div>')
page, n = re.subn(r'<div class="technical-info">\s*<div class="technical-info-content">\s*<h3>Praktické informace</h3>(.*?)</div>\s*</div>',
                  info_rows, page, count=1, flags=re.S)
assert n == 1

# O mně, shorter (owner, 12 Nov): first paragraph shown, the rest behind "Číst dál" (native <details>, no JS);
# the publication moves to "Profesní členství a reference", next to the ČAP membership
m = re.search(r'(<div class="about-description">\s*<p>.*?</p>)(.*?)(\n\s*</div>\n\s*</div>\n\s*<div class="credentials">)', page, flags=re.S)
page = (page[:m.start()] + m.group(1) +
        '\n                        <details class="about-more">\n                            <summary><span class="about-more-open">Číst dál</span><span class="about-more-close">Skrýt</span></summary>' +
        m.group(2) + '\n                        </details>' + m.group(3) + page[m.end():])
m = re.search(r'\n\s*<article class="publication-feature".*?</article>', page, flags=re.S)
pub = m.group(0)
page = page[:m.start()] + page[m.end():]
sub('\n            <h3 class="featured-subtitle">Kde mě najdete online</h3>', pub + '\n\n            <h3 class="featured-subtitle">Kde mě najdete online</h3>')

# Kontakt = 1G from ui-lab/kontakt-1.html (owner, 12 Nov): no card, no booking button, full-width hairline rows
# with the same measures as Praktické informace (.info-row: 20px rows, 14rem label column from 769 px)
m = re.search(r'<div class="contact-compact">.*?(?=\n\s*</div>\n\s*</section>)', page, flags=re.S)
assert m and 'contact-booking' in m.group(0)
CONTACT_ROWS = """<dl class="contact-rows">
                <div class="contact-row"><dt>E-mail</dt><dd><a href="mailto:kristyna@khgporadenstvi.cz">kristyna@khgporadenstvi.cz</a></dd></div>
                <div class="contact-row"><dt>Telefon</dt><dd><a href="tel:+420605990980">+420 605 990 980</a></dd></div>
                <div class="contact-row"><dt>Osobní setkání</dt><dd><a href="https://www.google.com/maps/dir/?api=1&amp;destination=V%C3%ADtkovick%C3%A1+3299%2F3a%2C+702+00+Ostrava" target="_blank" rel="noopener noreferrer">Vítkovická 3299/3a, 702 00 Ostrava</a></dd></div>
            </dl>"""
assert 'tel:+420605990980' in m.group(0) and 'destination=V%C3%ADtkovick%C3%A1+3299%2F3a' in m.group(0)
page = page[:m.start()] + CONTACT_ROWS + page[m.end():]

# lab only: menu links point at "/#sluzby" (the live homepage); keep them on this page
def lab_links(html):
    html = re.sub(r'href="/#(o-mne|sluzby|kontakt|faq)"', r'href="/ui-lab/proposal/#\1"', html)
    html = re.sub(r'href="/?((?:blog|quiz|kviz|cenik)[\w-]*\.html)', r'href="/ui-lab/proposal/\1', html)
    return re.sub(r'(<a\b[^>]*?)href="/"', r'\1href="/ui-lab/proposal/"', html)   # not the <base>

page = lab_links(page)

# ČAP logo is 350x340, not square: reserve its real ratio so nothing moves when it loads
sub('class="cap-logo-img" width="120" height="120"', 'class="cap-logo-img" width="120" height="117"')

# order: the questions a first-time client asks, in turn —
# is it for me? -> who is she? -> do others trust her? -> what, how much? -> how does it go? -> online/in person -> doubts -> credentials -> contact
markers = {
    "about": "    <!-- About Section -->",
    "services": "    <!-- Services Section -->",
    "format": "    <!-- Therapy Format Section -->",
    "how": "    <!-- How It Works Section -->",
    "who": "    <!-- Who Is It For Section -->",
    "faq": "    <!-- FAQ Section -->",
    "featured": "    <!-- Featured Section -->",
    "reviews": "    <!-- LAB: Phantom-style review cards",
    "contact": "    <!-- Contact Section -->",
}
start = page.index(markers["about"])
end = page.index("    </main>")
pos = sorted((page.index(m), k) for k, m in markers.items())
chunks = {}
for i, (p, k) in enumerate(pos):
    q = pos[i + 1][0] if i + 1 < len(pos) else end
    chunks[k] = page[p:q].rstrip("\n") + "\n\n"
order = ["about", "who", "reviews", "services", "how", "format", "faq", "featured", "contact"]  # owner, 12 Nov: O mně first
assert sorted(order) == sorted(chunks)
page = page[:start] + "".join(chunks[k] for k in order) + page[end:]

# ---- logo (owner, 12 Nov: F, the coral heart + name) as live text, no image request ----
HEART_D = (here / "icons" / "heart.d").read_text().strip()
HEART_VB = (here / "icons" / "heart.vb").read_text().strip()
LOGO_IMG = '<img src="/images/logo.webp" width="381" height="96" alt="" class="logo-img">'
BRAND_TEXT = '<span class="brand-text"><span class="brand-name">Kristýna Hriciková Glattová</span> <span class="brand-role">Psycholog a terapeut</span></span>'
LOGO_F = ('<span class="brand brand-f"><svg class="brand-heart" viewBox="' + HEART_VB + '" width="24" height="24" aria-hidden="true" focusable="false"><path d="' + HEART_D + '"/></svg>' + BRAND_TEXT + '</span>')
BRAND_CSS = """
    <style id="brand">
    /* LAB logo as text: Fraunces name + Inter role; heart is the owner's own drawing, traced to a vector */
    .nav-logo a{display:block;line-height:1;text-decoration:none;border-radius:8px}
    .brand{display:flex;align-items:center;gap:10px;min-height:44px}
    .brand-heart{flex:none;width:24px;height:24px;fill:#E8927C}
    .brand-text{display:block;min-width:0}
    .brand-name{display:block;font:500 17px/1.15 Fraunces,"Fraunces Fallback","Fraunces Fallback Android",Georgia,serif;color:#163A55;letter-spacing:-.005em;white-space:nowrap;font-synthesis:none}
    .brand-role{display:block;margin-top:4px;font:500 10.5px/1 Inter,"Inter Fallback","Inter Fallback Android",sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#5E6E7C}
    @media (min-width:969px){.brand-name{font-size:19px}.brand-heart{width:26px;height:26px}}
    @media (max-width:400px){.nav{padding-left:16px!important;padding-right:16px!important}}
    @media (hover:hover){.nav-logo a:hover .brand-name{text-decoration:underline;text-underline-offset:3px;text-decoration-thickness:1px}}
    </style>"""
FAVICON_OLD = '<link rel="icon" type="image/webp" href="images/favicon.webp">'
FAV_F = ('<link rel="icon" href="ui-lab/proposal/icons/heart.svg" type="image/svg+xml">\n    <link rel="icon" href="ui-lab/proposal/icons/heart-32.png" sizes="32x32" type="image/png">\n'
         '    <link rel="icon" href="ui-lab/proposal/icons/heart-96.png" sizes="96x96" type="image/png">\n'
         '    <link rel="apple-touch-icon" href="ui-lab/proposal/icons/heart-180.png">')

def brand(html, logo, fav):
    assert LOGO_IMG in html and FAVICON_OLD in html
    html = html.replace(LOGO_IMG, logo, 1).replace(FAVICON_OLD, fav, 1)
    return html.replace("</head>", BRAND_CSS + "\n</head>", 1)

page = brand(page, LOGO_F, FAV_F)

(here / "index.html").write_text(page, encoding="utf-8")
print("built", here / "index.html")


# ---- Ceník: the live cenik.html with the same theme layer plus ui-lab/proposal/cenik.css ----
def build_cenik():
    p = (root / "cenik.html").read_text(encoding="utf-8")

    def s(old, new):
        nonlocal p
        assert old in p, f"cenik: not found: {old[:60]}"
        p = p.replace(old, new, 1)

    s("<head>", '<head>\n    <base href="/">\n    <meta name="robots" content="noindex">')
    p = re.sub(r'\s*<link rel="preload" href="fonts/Playfair_Display/[^>]+>', "", p)
    s("css/styles.css?v=20261110-3", "ui-lab/proposal/styles.css?v=lab1")
    s('<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>',
      '<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>\n    ' + FRAUNCES_PRELOAD)
    s("</head>", "    <style>" + FRAUNCES_FACES + "</style>\n</head>")
    s("</head>", '    <style id="proposal">\n' + css + "\n" + (here / "cenik.css").read_text(encoding="utf-8") + "\n    </style>\n</head>")


    # cards and booking window: the format leads the title, no separate label (owner, 12 Nov)
    s('<p class="pricing-card-label">Online</p>\n                        <h2>Online terapie</h2>', '<h2>Online</h2>')
    s('<p class="pricing-card-label">Ostrava</p>\n                        <h2>Osobní terapie</h2>', '<h2>Osobně v Ostravě</h2>')
    s('<span class="booking-option-label">Online</span>\n                    <span class="booking-option-title">Terapie online</span>',
      '<span class="booking-option-title">Online</span>')
    s('<span class="booking-option-label">Ostrava</span>\n                    <span class="booking-option-title">Terapie osobně</span>',
      '<span class="booking-option-title">Osobně v Ostravě</span>')

    # Praktické informace and První setkání leave the prices section and become bands of their own
    info = re.search(r'\n\s*<section class="pricing-information".*?</section>', p, flags=re.S)
    first = re.search(r'\n\s*<section class="pricing-first-session".*?</section>', p, flags=re.S)
    info_html, first_html = info.group(0), first.group(0)
    p = p.replace(info_html, "", 1).replace(first_html, "", 1)
    s("        </section>\n    </main>",
      "        </section>\n\n        <section class=\"pricing-info-band\">\n            <div class=\"container\">" + info_html +
      "\n            </div>\n        </section>\n\n        <section class=\"pricing-cta-band\">\n            <div class=\"container\">" + first_html +
      "\n            </div>\n        </section>\n    </main>")

    # booking window and copy: as on the lab homepage
    p = re.sub(r"(?<=[a-zěščřžýáíéůúňťď,„(] )(Vy|Vám|Vás|Vámi|Váš|Vaše|Vašich|Vaší|Vašim|Vašimi|Vaši|Vašeho|Vaším|Vašem|Vašemu|Vašich)\b",
               lambda m: m.group(1).lower(), p)

    # lab links: menu and footer stay inside the lab
    p = lab_links(p)
    p = brand(p, LOGO_F, FAV_F)
    (here / "cenik.html").write_text(p, encoding="utf-8")
    print("built", here / "cenik.html")


build_cenik()


# ---- Blog and Kvízy (12 Nov): overview pages, every article, every quiz ----
# Layers: proposal.css + pages.css (shared parts) + one of blog.css / article.css / quiz.css.
VY = r"(?<=[a-zěščřžýáíéůúňťď,„(] )(Vy|Vám|Vás|Vámi|Váš|Vaše|Vašich|Vaší|Vašim|Vašimi|Vaši|Vašeho|Vaším|Vašem|Vašemu|Vašich)\b"
CTA_PANEL = """    <section class="blog-cta">
        <div class="container">
            <div class="cta-section">
                <p class="cta-heading">První setkání</p>
                <p>50 minut, 1 100 Kč, online nebo osobně na Vítkovické v Ostravě.</p>
                <a href="#" class="btn btn-primary" onclick="openBookingModal(); return false;">Objednat se</a>
            </div>
        </div>
    </section>
"""


def build_page(name, layer):
    p = (root / name).read_text(encoding="utf-8")

    def s(old, new):
        nonlocal p
        assert old in p, f"{name}: not found: {old[:60]}"
        p = p.replace(old, new, 1)

    # head: lab page, Inter + Fraunces, the theme layers inline after the stylesheets
    s("<head>", '<head>\n    <base href="/">\n    <meta name="robots" content="noindex">')
    p, n = re.subn(r'\s*<link rel="preload" href="fonts/Playfair_Display/[^>]+>', "", p)
    assert n == 1, name
    p, n = re.subn(r'css/styles\.css\?v=[\w-]+', "ui-lab/proposal/styles.css?v=lab1", p)
    assert n == 1, name
    s('<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>',
      '<link rel="preload" href="fonts/Inter/Inter-LatinExt-var.woff2" as="font" type="font/woff2" crossorigin>\n    ' + FRAUNCES_PRELOAD)
    layers = "\n".join((here / f).read_text(encoding="utf-8") for f in ("pages.css", layer))
    s("</head>", "    <style>" + FRAUNCES_FACES + '</style>\n    <style id="proposal">\n' + css + "\n" + layers + "\n    </style>\n</head>")

    # booking window, as on the lab homepage
    s('<span class="booking-option-label">Online</span>\n                    <span class="booking-option-title">Terapie online</span>',
      '<span class="booking-option-title">Online</span>')
    s('<span class="booking-option-label">Ostrava</span>\n                    <span class="booking-option-title">Terapie osobně</span>',
      '<span class="booking-option-title">Osobně v Ostravě</span>')

    if layer == "blog.css":
        # the topic leaves the illustration and joins the date as one label line
        p, n = re.subn(r'\n\s*<span class="blog-category-tag">([^<]*)</span>(.*?)<time class="blog-date"([^>]*)>([^<]*)</time>',
                       r'\2<p class="blog-meta"><span class="blog-meta-topic">\1</span> <span aria-hidden="true">·</span> <time\3>\4</time></p>', p, flags=re.S)
        assert n >= 4, name
        if name == "kviz.html":
            # quizzes are not news: no date, only the topic
            p, n = re.subn(r' <span aria-hidden="true">·</span> <time[^>]*>[^<]*</time>', "", p)
            assert n == 4, name
            # "O kvízech": two plain sentences instead of bold-lead bullets
            p, n = re.subn(r'(<h2 id="quiz-overview-title">O kvízech</h2>).*?(<p class="quiz-overview-disclaimer">)',
                           r'''\1
                <p>Kvízy jsem připravila z odborných poznatků a ze zkušeností ze své praxe. Jsou zdarma a výsledek uvidíte hned, se srozumitelným vysvětlením.</p>
                \2''', p, flags=re.S)
            assert n == 1, name
        else:
            # the title is the link; "Číst více" under every card goes
            p, n = re.subn(r'<h2 class="blog-card-title">([^<]*)</h2>(.*?)\s*<a href="([^"]+)" class="blog-read-more">[^<]*</a>',
                           r'<h2 class="blog-card-title"><a href="\3" class="blog-card-link">\1</a></h2>\2', p, flags=re.S)
            assert n >= 10, name
        # the title band is shorter now, so the first row of illustrations is on the first screen: load it eagerly
        # (the LCP image must not be lazy); the first one, the only one visible on a phone, gets priority
        lazy = 'loading="lazy" decoding="async"'
        first_row = 3 if name == "blog.html" else 2
        assert p.count(lazy) >= first_row, name
        p = p.replace(lazy, 'fetchpriority="high" decoding="async"', 1).replace(lazy, 'decoding="async"', first_row - 1)
        # the logo already leads home, as on Ceník
        s('            <a href="/" class="back-link">← Zpět na hlavní stránku</a>\n', "")
        # booking: the same panel and facts as on the homepage
        p, n = re.subn(r'    <section class="blog-cta">.*?</section>\n', lambda m: CTA_PANEL, p, count=1, flags=re.S)
        assert n == 1, name
        # no entrance animation: the reveal script is not loaded (one request fewer)
        p = p.replace('    <script src="js/card-reveal.js"></script>\n', "")
        if name == "blog.html":
            # owner, 12 Nov: no category filter for 10 articles; the list is short enough to read whole.
            # The filter row, its empty state and its script go (one request fewer); the topic stays on each card.
            p, n = re.subn(r'\n\s*<!-- Categories Filter -->\s*<div class="blog-categories">.*?</div>\n', "\n", p, flags=re.S)
            assert n == 1, name
            p, n = re.subn(r'\n\s*<!-- Empty State[^>]*-->\s*<div class="blog-empty".*?</button>\s*</div>', "", p, flags=re.S)
            assert n == 1, name
            p, n = re.subn(r'\s*<script src="js/blog-script\.js\?v=[\w-]+"></script>', "", p)
            assert n == 1, name
    else:
        s('<main id="main">', '<main id="main" class="doc">')

    if layer == "article.css":
        # owner, 12 Nov: one meta line under the title, name + one date. The job title moves to the author box;
        # "Aktualizováno" only when the article was really revised after it came out. JSON-LD keeps both dates.
        def meta(m):
            pub, pub_t, mod, mod_t = m.group(2), m.group(3), m.group(4), m.group(5)
            when = (f'<time datetime="{pub}">{pub_t}</time>' if pub == mod
                    else f'Aktualizováno <time datetime="{mod}">{mod_t}</time>')
            return f'{m.group(1)} <span aria-hidden="true">·</span> <span class="blog-post-date">{when}</span></p>'
        p, n = re.subn(r'(<p class="blog-post-byline"><a href="/#o-mne">[^<]*</a>) · [^<]*</p>\s*'
                       r'<p class="blog-post-date">Publikováno: <time datetime="([\d-]+)">([^<]*)</time> · '
                       r'Aktualizováno: <time datetime="([\d-]+)">([^<]*)</time></p>', meta, p)
        assert n == 1, name
        # author box: two short sentences, the credentials a reader checks first; the rest is one tap away on "O mně"
        p, n = re.subn(r'<p><strong>Mgr\. Kristýna Hriciková Glattová</strong> je psycholog.*?</p>',
                       '<p><strong>Mgr. Kristýna Hriciková Glattová</strong> je psycholog ve zdravotnictví ve Fakultní nemocnici Ostrava '
                       'a terapeut pro děti a dospělé. Je ve specializačním vzdělávání v oboru klinická psychologie, je členkou Asociace klinických psychologů '
                       'a kandidátní členkou České asociace pro psychoterapii.</p>',
                       p, flags=re.S)
        assert n == 1, name

    if layer == "quiz.css":
        # steps forward are navy; coral stays for booking
        s('class="btn btn-large quiz-start-btn"', 'class="btn btn-navy quiz-start-btn"')
        s('class="btn btn-primary quiz-btn-next"', 'class="btn btn-navy quiz-btn-next"')
        # result: label, title and description sit together in one panel, so it reads as "this is your result"
        desc = '<div class="quiz-results-description" id="resultsDescription"></div>'
        p, n = re.subn(r'\n\s*' + re.escape(desc), "", p)
        assert n == 1, name
        p, n = re.subn(r'(<h2 class="quiz-results-title" id="resultsTitle">[^<]*</h2>)', r'\1\n                        ' + desc, p)
        assert n == 1, name
        # the quiz script with lowercase "vy", as in the page text
        m = re.search(r'js/(quiz-[\w-]+\.js)\?v=[\w-]+', p)
        js = (root / "js" / m.group(1)).read_text(encoding="utf-8")
        (here / "js").mkdir(exist_ok=True)
        # result tips: the first three, not seven
        js, n = re.subn(r'result\.recommendations\.forEach', 'result.recommendations.slice(0, 3).forEach', js)
        assert n == 1, name
        (here / "js" / m.group(1)).write_text(re.sub(VY, lambda k: k.group(1).lower(), js), encoding="utf-8")
        p = p.replace(m.group(0), "ui-lab/proposal/js/" + m.group(1) + "?v=lab1")

    p = re.sub(VY, lambda k: k.group(1).lower(), p)
    p = lab_links(p)
    p = brand(p, LOGO_F, FAV_F)
    (here / name).write_text(p, encoding="utf-8")


pages = [("blog.html", "blog.css"), ("kviz.html", "blog.css")]
pages += [(f.name, "article.css") for f in sorted(root.glob("blog-*.html"))]
pages += [(f.name, "quiz.css") for f in sorted(root.glob("quiz-*.html"))]
for name, layer in pages:
    build_page(name, layer)
print("built", len(pages), "blog and quiz pages")
