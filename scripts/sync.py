"""Keeps the copied parts of the static pages in step. Replaces sync-header.ps1 and sync-footer.ps1 (23 Nov).

  python3 scripts/sync.py           write: header, footer and version stamps into every page
  python3 scripts/sync.py --check   only report what is out of step (exit 1); check-indexing.py runs this

1. Header: partials/header.html into every top-level page that has <header class="header">, with the menu link of
   the page's section marked active (Ceník, Blog, Kvízy).
2. Footer: partials/footer.html into every top-level page.
3. Version stamps: every local .js a page (or rezervace/*.js) links to gets ?v=<first 8 hex of its SHA-256>.
   A changed file gets a new address, so browsers can keep JS for a year (.htaccess) and still never see an
   old copy. Never edit ?v= by hand: run this script after changing a JS file.
4. CSS: all of a page's CSS is the one inline <style id="proposal">, built from partials/theme/ (23 Nov; before,
   css/*.css files overridden by hand-kept inline copies). Inline on purpose: no extra request, nothing
   render-blocking. Every page gets base.css (the homepage home-first.css before it) (+ its base-* part) + core.css + its page part(s) + tail.css, see
   theme_for(); edit those files, never the block in a page. A page may not link a stylesheet (check fails).
5. LCP preload: a page with an <img fetchpriority="high"> (not inside <picture>) gets a matching
   <link rel="preload" as="image" data-sync="lcp"> just before its CSS, so the browser starts that image before it
   has read the inline CSS. The homepage keeps its hand-written preload (its portrait is a <picture>).
"""
import hashlib, pathlib, re, sys

root = pathlib.Path(__file__).resolve().parent.parent
CHECK = "--check" in sys.argv[1:]

HEADER_RE = re.compile(r'<header\b[^>]*class="header"[^>]*>[\s\S]*?</header>', re.I)
STYLESHEET_RE = re.compile(r'<link\b[^>]*rel="(?:stylesheet|preload)"[^>]*as="style"|<link\b[^>]*rel="stylesheet"', re.I)
LCP_LINK_RE = re.compile(r'\n[ \t]*<link rel="preload" as="image"[^>]*data-sync="lcp">')
LCP_IMG_RE = re.compile(r'<img\b[^>]*\bfetchpriority="high"[^>]*>')
THEME_RE = re.compile(r'(<style id="proposal">)([\s\S]*?)(</style>)')
FOOTER_RE = re.compile(r'<footer\b[^>]*>[\s\S]*?</footer>', re.I)
# a quoted local path to a .css or .js file, with or without its stamp
ASSET_RE = re.compile(r'(?P<q>["\'])(?P<path>(?:\.\./|/)?(?:[\w-]+/)*[\w.-]+\.(?:css|js))(?:\?v=[\w-]*)?(?P=q)')


def read(p):
    return p.read_text(encoding="utf-8")


def partial(name):
    return read(root / "partials" / name).rstrip("\r\n")


def indented(template, content, start):
    """The partial with every line after the first moved to the indent the old block started at."""
    line_start = content.rfind("\n", 0, start) + 1
    indent = content[line_start:start]
    if indent.strip():
        raise SystemExit(f"block does not start on its own line (at character {start})")
    lines = template.split("\n")
    return "\n".join([lines[0]] + [indent + l if l else "" for l in lines[1:]])


def header_for(name):
    section, current = "", ""
    if name == "cenik.html":
        section, current = "CENIK", "page"
    elif name == "blog.html":
        section, current = "BLOG", "page"
    elif name.startswith("blog-"):
        section, current = "BLOG", "location"
    elif name == "kviz.html":
        section, current = "QUIZ", "page"
    elif name.startswith("quiz-"):
        section, current = "QUIZ", "location"
    out = partial("header.html")
    for s in ("CENIK", "BLOG", "QUIZ"):
        on = s == section
        out = out.replace("{{%s_ACTIVE}}" % s, " active" if on else "")
        out = out.replace("{{%s_CURRENT}}" % s, f' aria-current="{current}"' if on else "")
    if re.search(r"{{[A-Z_]+}}", out):
        raise SystemExit(f"unresolved header placeholder for {name}")
    return out


def theme_part(name):
    text = read(root / "partials" / "theme" / f"{name}.css")
    return text[:-1] if text.endswith("\n") else text


def theme_for(name):
    """All CSS of a page, in cascade order: base (+ its base-* part), core, the page's own part(s), tail.
    None = the page has no CSS block (the booking admin pages under rezervace/ carry their own)."""
    if name == "index.html":
        base, own = [], ["home"]
    elif name == "cenik.html":
        base, own = ["base-cenik"], ["cenik"]
    elif name in ("blog.html", "kviz.html"):
        base, own = ["base-blog-list"], ["subpage", "blog-list"]
    elif name.startswith("blog-"):
        base, own = ["base-blog-list", "base-blog-post"], ["subpage", "blog-post"]
    elif name.startswith("quiz-"):
        base, own = ["base-quiz"], ["subpage", "quiz"]
    elif name == "404.html":
        base, own = [], ["subpage", "404"]
    elif name in ("obchodni-podminky.html", "ochrana-osobnich-udaju.html", "rezervace.html"):
        base, own = [], ["subpage", "legal"]
    else:
        return None
    first = ["home-first"] if name == "index.html" else []
    return "\n".join(theme_part(p) for p in [*first, "base", *base, "core", *own, "tail"])


def lcp_preload(content):
    """Drop the generated preload, then add it again from the page's high-priority image (if any)."""
    content = LCP_LINK_RE.sub("", content)
    m = LCP_IMG_RE.search(content)
    if not m or "<picture" in content[max(0, m.start() - 600):m.start()]:
        return content
    attr = lambda n: (re.search(rf'\s{n}="([^"]*)"', m[0]) or [None, None])[1]
    link = f'<link rel="preload" as="image" href="{attr("src")}"'
    if attr("srcset"):
        link += f' imagesrcset="{attr("srcset")}" imagesizes="{attr("sizes")}"'
    link += ' fetchpriority="high" data-sync="lcp">'
    at = content.index('<style id="proposal">')
    line_start = content.rindex("\n", 0, at)
    indent = content[line_start + 1:at]
    return content[:line_start] + "\n" + indent + link + content[line_start:]


def replace_one(rx, content, template, name, what):
    found = list(rx.finditer(content))
    if len(found) != 1:
        raise SystemExit(f"{name}: expected one {what}, found {len(found)}")
    m = found[0]
    return content[:m.start()] + indented(template, content, m.start()) + content[m.end():]


def stamp(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()[:8]


def stamp_assets(file, content):
    missing = []

    def sub(m):
        rel = m.group("path")
        target = (root / rel.lstrip("/")) if rel.startswith("/") else (file.parent / rel)
        if not target.resolve().is_file():
            missing.append(rel)
            return m.group(0)
        return f'{m["q"]}{rel}?v={stamp(target)}{m["q"]}'

    out = ASSET_RE.sub(sub, content)
    if missing:
        raise SystemExit(f"{file.relative_to(root)}: links to missing files {missing}")
    return out


changed = []


def save(file, old, new):
    if new != old:
        changed.append(str(file.relative_to(root)))
        if not CHECK:
            file.write_text(new, encoding="utf-8", newline="")


# JS that links to CSS first (rezervace/site-header.js loads the site's stylesheets), so the pages then stamp the
# JS by its final content
for js in sorted((root / "rezervace").glob("*.js")):
    old = read(js)
    save(js, old, stamp_assets(js, old))

pages = sorted(root.glob("*.html")) + sorted((root / "rezervace").glob("*.html"))
for page in pages:
    old = read(page)
    if "\r\n" in old:
        raise SystemExit(f"{page.name}: Windows line endings, convert to LF first")
    new = old
    if page.parent == root:
        if HEADER_RE.search(new):
            new = replace_one(HEADER_RE, new, header_for(page.name), page.name, "header")
        new = replace_one(FOOTER_RE, new, partial("footer.html"), page.name, "footer")
        theme = theme_for(page.name)
        found = len(THEME_RE.findall(new))
        if found != (0 if theme is None else 1):
            raise SystemExit(f"{page.name}: expected {0 if theme is None else 1} theme block, found {found} "
                             "(a new page needs an entry in theme_for())")
        if theme is not None:
            new = THEME_RE.sub(lambda m: m[1] + theme + m[3], new)
            if page.name != "index.html":
                new = lcp_preload(new)
        if STYLESHEET_RE.search(new):
            raise SystemExit(f"{page.name}: links a stylesheet; all CSS belongs in partials/theme/ (see theme_for())")
    new = stamp_assets(page, new)
    save(page, old, new)

if CHECK:
    if changed:
        print("  FAIL  out of step (run python3 scripts/sync.py): " + ", ".join(changed))
        sys.exit(1)
    print(f"  ok    header, footer, theme and version stamps in step ({len(pages)} pages)")
else:
    print(f"updated {len(changed)} file(s)" + (": " + ", ".join(changed) if changed else ""))
