#!/usr/bin/env python3
"""Assemble the test pages from the live site files. Nothing here is deployed.

  variant A  /ui-lab/impeccable/              homepage, impeccable.css
  variant B  /ui-lab/impeccable/b/            homepage, b.css + b.js
             /ui-lab/impeccable/b/cenik.html  pricing page, b.css + b.js

Head, header, footer and the booking window come from ../../index.html and
../../cenik.html; the homepage <main> is replaced by main.html.
"""
import os, re

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(HERE))
B = "/ui-lab/impeccable/b/"

HILLS = """<svg class="k-hills%s" viewBox="0 0 1440 220" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <path class="k-hill-1" d="M0 120 C 220 40 420 60 640 110 S 1080 190 1440 70 V220 H0 Z"/>
            <path class="k-hill-2" d="M0 170 C 260 90 520 150 760 150 S 1200 80 1440 140 V220 H0 Z"/>
            <path class="k-hill-3" d="M0 200 C 300 150 560 210 860 185 S 1260 150 1440 190 V220 H0 Z"/>
        </svg>"""
LOGO_OLD = '<img src="/images/logo.webp" width="381" height="96" alt="" class="logo-img">'
LOGO_A = '<img src="/images/logo.png" width="458" height="154" alt="" class="logo-img">'
LOGO_B = ('<span class="k-logo"><span class="k-logo-heart"></span><span class="k-logo-text">'
          '<b>Kristýna Hriciková Glattová</b><small>Psycholog a terapeut</small></span></span>')


def read(*parts):
    with open(os.path.join(*parts), encoding="utf-8") as f:
        return f.read()


def write(rel, html):
    path = os.path.join(HERE, rel)
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        f.write(html)
    print("wrote", path, len(html), "bytes")


def rooted(html):
    """Root-relative assets, so a page works from any folder."""
    return re.sub(r'(?<![/\w.-])(images|css|js|fonts)/', r'/\1/', html)


def with_css(html, css):
    return html.replace(
        "</head>",
        '    <link rel="stylesheet" href="/ui-lab/impeccable/%s">\n'
        '    <meta name="robots" content="noindex">\n</head>' % css, 1)


def variant_b(html, home):
    """Shared variant-B chrome: logo, footer hills, script, links that stay in the test."""
    assert LOGO_OLD in html
    html = html.replace(LOGO_OLD, LOGO_B, 1)
    html = html.replace('<footer class="footer">', '<footer class="footer">\n        ' + HILLS % " k-hills--foot", 1)
    html = html.replace("</body>", '<script src="/ui-lab/impeccable/b.js"></script>\n</body>', 1)
    html = html.replace('href="/cenik.html"', 'href="%scenik.html"' % B)
    html = html.replace('href="cenik.html"', 'href="%scenik.html"' % B)
    html = html.replace('href="/"', 'href="%s"' % B)
    html = html.replace('href="/#', 'href="#' if home else 'href="%s#' % B)
    return with_css(html, "b.css")


# ---- homepage ----
src = read(ROOT, "index.html")
start = src.index('<main id="main">')
end = src.index("</main>") + len("</main>")
home = rooted(src[:start].rstrip(" ") + read(HERE, "main.html").strip() + src[end:])

a = home.replace(LOGO_OLD, LOGO_A, 1).replace('href="/#', 'href="#')
write("index.html", with_css(a, "impeccable.css"))
write(os.path.join("b", "index.html"), variant_b(home, home=True))

# ---- pricing page (variant B) ----
cenik = rooted(read(ROOT, "cenik.html"))
MAIN = '<main id="main">'
HERO_END = '</div>\n        </section>\n\n        <section class="pricing-main"'
assert MAIN in cenik and HERO_END in cenik
cenik = cenik.replace(MAIN, '<main id="main" class="k-main k-cenik">', 1)
cenik = cenik.replace(HERO_END, '</div>\n            ' + HILLS % "" + '\n        </section>\n\n        <section class="pricing-main"', 1)
write(os.path.join("b", "cenik.html"), variant_b(cenik, home=False))
