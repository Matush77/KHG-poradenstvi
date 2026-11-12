"""Check that every public page stays indexable. See docs/REDESIGN-SHIP.md.

  python3 scripts/check-indexing.py          the files in this checkout (run before pushing to main)
  python3 scripts/check-indexing.py --live   the same pages on https://khgporadenstvi.cz (run after the deploy)

Exit code 0 = all passed. Public pages = every URL in sitemap.xml (404.html is meant to be noindex and is not listed).
"""
import pathlib, re, subprocess, sys, urllib.error, urllib.request

SITE = "https://khgporadenstvi.cz"
root = pathlib.Path(__file__).resolve().parent.parent
failed = False


def bad(msg):
    global failed
    failed = True
    print(f"  FAIL  {msg}")


def ok(msg):
    print(f"  ok    {msg}")


def check_html(label, html, expected_canonical):
    before = failed
    if re.search(r'<meta[^>]+name="(?:robots|googlebot)"[^>]*content="[^"]*(?:noindex|nofollow|none)', html, re.I):
        bad(f"{label}: robots meta says noindex/nofollow")
    if re.search(r"<base\b", html, re.I):
        bad(f"{label}: has a <base> tag (lab only)")
    if "ui-lab" in html:
        bad(f"{label}: still points into ui-lab/")
    canon = re.findall(r'<link rel="canonical" href="([^"]*)"', html)
    if canon != [expected_canonical]:
        bad(f"{label}: canonical {canon or 'missing'}, expected {expected_canonical}")
    if failed == before:
        ok(label)


def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": "khg-indexing-check"})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return r.status, dict(r.headers), r.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, dict(e.headers), ""


urls = re.findall(r"<loc>([^<]+)</loc>", (root / "sitemap.xml").read_text(encoding="utf-8"))
if not urls:
    sys.exit("no <loc> in sitemap.xml")

if "--live" not in sys.argv:
    print("Local files")
    for u in urls:
        f = u[len(SITE) + 1:] or "index.html"
        p = root / f
        if not p.is_file():
            bad(f"{f}: in sitemap but missing")
            continue
        check_html(f, p.read_text(encoding="utf-8"), u)
    hits = [str(p.relative_to(root)) for p in [*root.glob("css/*.css"), *root.glob("js/*.js")] if "ui-lab" in p.read_text(encoding="utf-8")]
    bad(f"ui-lab referenced in {hits}") if hits else ok("css/ and js/ free of ui-lab")
    robots = (root / "robots.txt").read_text(encoding="utf-8")
    bad("robots.txt disallows the whole site") if re.search(r"^\s*Disallow:\s*/\s*$", robots, re.M) else ok("robots.txt allows the site")
    bad(".htaccess sets X-Robots-Tag, check it") if "x-robots-tag" in (root / ".htaccess").read_text(encoding="utf-8").lower() else ok(".htaccess sends no X-Robots-Tag")
    git = lambda *a: subprocess.run(["git", *a], cwd=root, capture_output=True, text=True).stdout.strip()
    n, branch = len(git("ls-files", "ui-lab").splitlines()), git("branch", "--show-current")
    if n == 0:
        ok("ui-lab/ not tracked")
    elif branch == "main":
        bad(f"ui-lab/ is tracked on main ({n} files): it would be deployed")
    else:
        print(f"  note  ui-lab/ is tracked on '{branch}' ({n} files); fine here, must be 0 on main")
else:
    print(f"Live site ({SITE})")
    for u in urls:
        status, headers, body = fetch(u)
        if status != 200:
            bad(f"{u}: HTTP {status}")
            continue
        xr = next((v for k, v in headers.items() if k.lower() == "x-robots-tag"), "")
        if re.search(r"noindex|none", xr, re.I):
            bad(f"{u}: X-Robots-Tag header '{xr}'")
        check_html(u, body, u)
    status, _, _ = fetch(SITE + "/ui-lab/proposal/")
    ok("ui-lab/ not on the server") if status == 404 else bad(f"{SITE}/ui-lab/proposal/ answers HTTP {status} (lab is deployed)")

print()
print("PASS: all pages indexable" if not failed else "STOP: fix the FAIL lines")
sys.exit(1 if failed else 0)
