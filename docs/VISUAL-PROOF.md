# CI and the visual proof

## What runs on GitHub (`.github/workflows/ci.yml`)

On every push to `main` (and on pull requests), GitHub Actions runs two jobs. They never deploy: going live
stays the manual Deploy button in the hosting panel.

| Job | What it checks | Fails when |
|---|---|---|
| Site checks and booking tests | `scripts/check-indexing.py` (header, footer, CSS and stamps in step; every page indexable), `php booking/tests/run.php` (420 offline checks, dummy keys) | any check fails |
| Visual proof | screenshots of every page, before (the previous commit) vs after (the pushed commit) | a page does not load or throws a JavaScript error |

A visual difference does **not** fail the run: a design change is meant to differ. The run's summary page
lists what differs; the "visual-diff" artifact holds the pictures (changed pixels in red). For a change that
should look the same (cleanup, refactor), the list must be empty.

Results: GitHub → the repository → Actions. The visual job takes about 15–20 minutes.

## The visual proof (`scripts/visual/`)

`proof.py` serves the site twice with PHP's built-in server (before = a commit unpacked with `git archive`,
after = the working tree) and `shoot.py` takes screenshots with Playwright (headless Chromium):

- every top-level page and `rezervace/zruseni.html`, at 20 widths from 320 to 1280 px, full page;
- states: after scrolling, menu open (phone widths), homepage FAQ / more reviews / gallery lightbox, quiz
  start / first answer / results (first and last answers), booking calendar / day / time / form errors
  (with and without `?forma=osobne`), and every element forced into `:hover` and into `:focus-visible`;
- also the computed transition, cursor, outline, z-index etc. of every element (not visible in a still picture).

Each side is shot twice; an image counts as different only if no "before" shot equals any "after" shot. Tiny
differences (≤ 300 px) are listed apart: so far always the one-pixel edge of the hidden skip link.

Booking: CI has no Google calendar, so the booking page shows its error message and only that state is
compared. On your own machine `--real-booking` reads the real free times (read-only, `~/.config/khg`).

## Running it locally

Needs Python with `playwright` and `pillow` plus Playwright's Chromium (about 700 MB in
`~/.cache/ms-playwright`). On the owner's machine that is the virtual environment `ui-lab/cascade/.venv`
(set up 23 Nov); elsewhere: `python3 -m venv .venv && .venv/bin/pip install playwright pillow &&
.venv/bin/python -m playwright install chromium`.

```bash
ui-lab/cascade/.venv/bin/python scripts/visual/proof.py                     # working tree vs last commit
ui-lab/cascade/.venv/bin/python scripts/visual/proof.py --before 759f08f    # vs any commit
ui-lab/cascade/.venv/bin/python scripts/visual/proof.py --pages index.html,cenik.html --real-booking
```

Output: `scripts/visual/shots/report.md` and `scripts/visual/shots/diff/` (ignored by git). About 6 minutes
for the whole site on 12 cores. Ports 8880 and 8881 must be free.
