"""Build ui-lab/reviews/index.html: the live homepage with the reviews section
swapped for the Phantom-style card row in section.html. Run: python3 ui-lab/reviews/build.py"""
import pathlib, re

here = pathlib.Path(__file__).parent
root = here.parent.parent
page = (root / "index.html").read_text(encoding="utf-8")
section = (here / "section.html").read_text(encoding="utf-8")

page = page.replace("<head>", '<head>\n    <base href="/">\n    <meta name="robots" content="noindex">', 1)
page, n = re.subn(r"    <!-- Testimonials Section -->.*?</section>\n", lambda m: section, page, count=1, flags=re.S)
assert n == 1, "testimonials section not found"
(here / "index.html").write_text(page, encoding="utf-8")
print("built", here / "index.html")
