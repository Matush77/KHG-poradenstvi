"""Visual proof: does the site look the same as at another commit? See docs/VISUAL-PROOF.md.

python3 scripts/visual/proof.py [--before REF] [--pages a.html,b.html] [--strict] [--real-booking]

- "before" = the site at REF (default HEAD, i.e. the last commit), unpacked with git archive into a temp folder;
  "after" = the working tree as it is now. In CI, REF is the previous commit of the push.
- Both are served by PHP's built-in server with booking/router.php and a throwaway booking config (dummy keys,
  no e-mail, nothing real touched), on ports 8881 (before) and 8880 (after). Without Google the booking page shows
  its error message, so only that state is compared. --real-booking (local machine only) uses ~/.config/khg
  instead: the real free times are read (read-only), so the calendar, day, time and form steps are compared too.
- Each side is shot twice (shoot.py). An image counts as different only if no "before" shot equals any "after"
  shot, which filters the rare one-pixel flicker of a single run.
- Writes shots/report.md (also to $GITHUB_STEP_SUMMARY in CI) and, for each differing image, a diff picture in
  shots/diff/ (the "after" image with changed pixels in red).
- Exit code: 1 if a page failed to load or threw a JavaScript error; with --strict also 1 if any image differs.
  Without --strict a difference is a report, not a failure (a design change is meant to differ).
"""
import argparse, os, pathlib, shutil, subprocess, sys, tempfile, time, urllib.request, json

HERE = pathlib.Path(__file__).resolve().parent
ROOT = HERE.parent.parent
SHOTS = pathlib.Path(os.environ.get('SHOTS_DIR', HERE / 'shots'))
PY = sys.executable
# small, known noise: a one-pixel row along the edge of the hidden skip link ("Přejít k hlavnímu obsahu",
# translateY(-100%) of a 44.05 px box) is smoothed differently between builds; layout and styles are identical
TINY = 300  # images with at most this many changed pixels are listed apart, as probably noise


def serve(docroot, port, cfg):
    env = dict(os.environ, KHG_CONFIG_DIR=cfg, PHP_CLI_SERVER_WORKERS='6')
    p = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(docroot), str(docroot / 'booking' / 'router.php')],
                         env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(50):
        try:
            urllib.request.urlopen(f'http://127.0.0.1:{port}/index.html', timeout=2)
            return p
        except Exception:
            time.sleep(0.2)
    p.kill()
    sys.exit(f'server on port {port} did not start')


def booking_config(d):
    """A throwaway config like booking/tests/run.php uses: dummy Stripe test key, no Google, no SMTP."""
    d.mkdir(parents=True, exist_ok=True)
    (d / 'stripe-test.env').write_text('STRIPE_SECRET_KEY=sk_test_dummy\nSTRIPE_PUBLISHABLE_KEY=pk_test_dummy\n')
    (d / 'booking-secret').write_text('ab' * 32)
    (d / 'booking.env').write_text('MAIL_OUTBOX=' + str(d / 'outbox') + '\n')
    return str(d)


def compare():
    from PIL import Image, ImageChops
    runs = {s: SHOTS / s for s in ('before1', 'before2', 'after1', 'after2')}
    same = flicker = 0
    diffs, tiny, missing = [], [], []
    for p in sorted(runs['before1'].rglob('*.png')):
        rel = p.relative_to(runs['before1'])
        B = {(runs[s] / rel).read_bytes() for s in ('before1', 'before2') if (runs[s] / rel).exists()}
        A = {(runs[s] / rel).read_bytes() for s in ('after1', 'after2') if (runs[s] / rel).exists()}
        if not A:
            missing.append(f'{rel} (not in after)'); continue
        if B & A:
            same += 1; flicker += len(B) > 1 or len(A) > 1; continue
        a, b = Image.open(p).convert('RGB'), Image.open(runs['after1'] / rel).convert('RGB')
        if a.size != b.size:
            diffs.append((rel, f'page size {a.size[0]}×{a.size[1]} → {b.size[0]}×{b.size[1]}')); continue
        m = ImageChops.difference(a, b).convert('L').point(lambda v: 255 if v else 0)
        n = m.histogram()[255]
        out = SHOTS / 'diff' / rel
        out.parent.mkdir(parents=True, exist_ok=True)
        Image.composite(Image.new('RGB', b.size, (255, 0, 0)), b, m).save(out)
        (tiny if n <= TINY else diffs).append((rel, f'{n} px in {m.getbbox()}'))
    for p in sorted(runs['after1'].rglob('*.png')):
        if not (runs['before1'] / p.relative_to(runs['after1'])).exists():
            missing.append(f'{p.relative_to(runs["after1"])} (new in after)')
    errors = []
    for s in sorted(runs['after1'].rglob('styles.json')):
        e = json.loads(s.read_text())['errors']
        if e:
            errors.append(f'{s.parent.name}: {e[0][:200]}')
    total = same + len(diffs) + len(tiny)
    lines = [f'## Visual proof', '',
             f'{total} screenshots compared: **{same} identical**'
             + (f' ({flicker} needed the second run)' if flicker else '')
             + f', **{len(diffs)} different**, {len(tiny)} tiny (≤ {TINY} px, usually edge smoothing).', '']
    if diffs:
        lines += ['### Different', ''] + [f'- `{r}`: {d}' for r, d in diffs[:80]] + ([f'- … and {len(diffs) - 80} more'] if len(diffs) > 80 else []) + ['']
    if tiny:
        lines += ['### Tiny', ''] + [f'- `{r}`: {d}' for r, d in tiny[:40]] + ['']
    if missing:
        lines += ['### Only on one side', ''] + [f'- `{m}`' for m in missing[:40]] + ['']
    if errors:
        lines += ['### JavaScript errors (after)', ''] + [f'- {e}' for e in errors] + ['']
    if diffs or tiny:
        lines += ['Diff pictures (changed pixels in red): `shots/diff/` (in CI: the "visual-diff" artifact).', '']
    return '\n'.join(lines), bool(diffs), bool(errors)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--before', default='HEAD')
    ap.add_argument('--pages', default='')
    ap.add_argument('--strict', action='store_true')
    ap.add_argument('--real-booking', action='store_true')
    args = ap.parse_args()
    if SHOTS.exists():
        shutil.rmtree(SHOTS)
    SHOTS.mkdir(parents=True)
    tmp = pathlib.Path(tempfile.mkdtemp(prefix='khg-visual-'))
    before = tmp / 'before'
    before.mkdir()
    subprocess.run(f'git -C "{ROOT}" archive {args.before} | tar -x -C "{before}"', shell=True, check=True)
    real = str(pathlib.Path.home() / '.config' / 'khg')
    cfg = (lambda n: real) if args.real_booking else (lambda n: booking_config(tmp / n))
    servers = [serve(before, 8881, cfg('cfg-before')), serve(ROOT, 8880, cfg('cfg-after'))]
    failed = False
    try:
        t0 = time.time()
        for name, port in (('before1', 8881), ('after1', 8880), ('before2', 8881), ('after2', 8880)):
            cmd = [PY, str(HERE / 'shoot.py'), name, str(port)] + ([args.pages] if args.pages else [])
            r = subprocess.run(cmd, env=dict(os.environ, SHOTS_DIR=str(SHOTS)))
            failed |= r.returncode != 0
            print(f'{name} done ({int(time.time() - t0)} s)', flush=True)
    finally:
        for s in servers:
            s.kill()
        shutil.rmtree(tmp, ignore_errors=True)
    report, differs, js_errors = compare()
    if failed:
        report += '\n**A page failed to load in one of the runs; see the log above.**\n'
    (SHOTS / 'report.md').write_text(report)
    if os.environ.get('GITHUB_STEP_SUMMARY'):
        with open(os.environ['GITHUB_STEP_SUMMARY'], 'a') as f:
            f.write(report + '\n')
    print(report)
    sys.exit(1 if failed or js_errors or (args.strict and differs) else 0)


if __name__ == '__main__':
    main()
