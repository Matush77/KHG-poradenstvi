"""Keeps the copied parts of the static pages in step. Replaces sync-header.ps1 and sync-footer.ps1 (23 Nov).

  python3 scripts/sync.py           write: header, footer and version stamps into every page
  python3 scripts/sync.py --check   only report what is out of step (exit 1); check-indexing.py runs this

1. Header: partials/header.html into every top-level page that has <header class="header">, with the menu link of
   the page's section marked active (Ceník, Blog, Kvízy).
2. Footer: partials/footer.html into every top-level page.
3. Version stamps: every local .css / .js a page (or rezervace/*.js) links to gets ?v=<first 8 hex of its SHA-256>.
   A changed file gets a new address, so browsers can keep CSS and JS for a year (.htaccess) and still never see an
   old copy. Never edit ?v= by hand: run this script after changing a CSS or JS file.
"""
import hashlib, pathlib, re, sys

root = pathlib.Path(__file__).resolve().parent.parent
CHECK = "--check" in sys.argv[1:]

HEADER_RE = re.compile(r'<header\b[^>]*class="header"[^>]*>[\s\S]*?</header>', re.I)
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
    new = stamp_assets(page, new)
    save(page, old, new)

if CHECK:
    if changed:
        print("  FAIL  out of step (run python3 scripts/sync.py): " + ", ".join(changed))
        sys.exit(1)
    print(f"  ok    header, footer and version stamps in step ({len(pages)} pages)")
else:
    print(f"updated {len(changed)} file(s)" + (": " + ", ".join(changed) if changed else ""))
