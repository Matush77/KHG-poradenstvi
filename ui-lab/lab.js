/* UI lab toolbar: Original / Polished / Ghost, grid overlay, inspector.
   Injected by ui-lab/serve.py. Not part of the site. */
(function () {
  'use strict';
  if (window.top !== window) return;               // never inside the ghost iframe
  const LS = 'uiLab';
  const state = Object.assign({ mode: 'polished', opacity: 0.5, blend: 'normal', align: 'section', grid: false, inspect: false, min: false },
    (() => { try { return JSON.parse(localStorage.getItem(LS)) || {}; } catch (e) { return {}; } })());
  const save = () => { try { localStorage.setItem(LS, JSON.stringify(state)); } catch (e) {} };
  const polish = document.getElementById('lab-polish');

  // The site defers its stylesheet on phones; make sure it is applied.
  document.querySelectorAll('link[rel=preload][as=style]').forEach(l => { l.rel = 'stylesheet'; });

  /* ---------- styles for the lab UI ---------- */
  const css = document.createElement('style');
  css.textContent = `
  #lab-ui{position:fixed;left:50%;bottom:max(12px,env(safe-area-inset-bottom));transform:translateX(-50%);z-index:2147483000;
    font:500 13px/1.3 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#f4f1ec;background:rgba(28,32,38,.92);
    backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.3);
    padding:8px;display:flex;flex-direction:column;gap:8px;width:max-content;max-width:calc(100vw - 16px)}
  #lab-ui *{box-sizing:border-box;font:inherit;margin:0}
  #lab-ui .row{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:center}
  #lab-ui button{appearance:none;border:0;border-radius:10px;padding:0 12px;min-height:36px;background:rgba(255,255,255,.08);color:inherit;cursor:pointer}
  #lab-ui button[aria-pressed=true]{background:#E8927C;color:#1c2026;font-weight:700}
  #lab-ui .seg{display:flex;background:rgba(255,255,255,.06);border-radius:12px;padding:2px;gap:2px}
  #lab-ui .seg button{background:transparent}
  #lab-ui .seg button[aria-pressed=true]{background:#f4f1ec;color:#1c2026}
  #lab-ui label{display:flex;gap:6px;align-items:center;white-space:nowrap}
  #lab-ui input[type=range]{width:110px;accent-color:#E8927C}
  #lab-ui select{background:rgba(255,255,255,.1);color:inherit;border:0;border-radius:8px;min-height:32px;padding:0 6px}
  #lab-ui .ghost-row{display:none}
  #lab-ui[data-mode=ghost] .ghost-row{display:flex}
  #lab-ui[data-min=true] .full{display:none}
  #lab-ui .tag{opacity:.6;font-size:11px;letter-spacing:.06em;text-transform:uppercase}
  #lab-ui a{color:#F5B5A5}
  @media (max-width:430px){#lab-ui{padding:6px;gap:6px}#lab-ui button{padding:0 9px;min-height:40px}#lab-ui .row{gap:4px}}
  #lab-ghost{position:absolute;left:0;top:0;width:100%;border:0;pointer-events:none;z-index:2147482000;background:transparent;will-change:transform}
  #lab-grid{position:fixed;inset:0;pointer-events:none;z-index:2147481000}
  #lab-grid .cols{position:absolute;top:0;bottom:0}
  #lab-grid .base{position:absolute;inset:0;background:repeating-linear-gradient(to bottom,transparent 0 7px,rgba(232,146,124,.18) 7px 8px)}
  #lab-insp{position:fixed;inset:0;pointer-events:none;z-index:2147482500}
  #lab-insp div{position:fixed}
  #lab-insp .m{background:rgba(246,178,107,.35)} #lab-insp .p{background:rgba(147,196,125,.45)} #lab-insp .c{background:rgba(111,168,220,.4)}
  #lab-insp .gap{background:rgba(232,72,85,.25);border:1px dashed #e84855;display:flex;align-items:center;justify-content:center}
  #lab-insp .gap span{background:#e84855;color:#fff;font:600 11px system-ui;padding:1px 5px;border-radius:6px}
  #lab-info{position:fixed;left:8px;right:8px;top:8px;margin-inline:auto;max-width:440px;z-index:2147483001;background:rgba(28,32,38,.95);color:#f4f1ec;
    font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace;border-radius:12px;padding:10px 12px;box-shadow:0 10px 30px rgba(0,0,0,.3);display:none;white-space:pre-wrap}
  `;
  document.head.appendChild(css);

  /* ---------- toolbar ---------- */
  const ui = document.createElement('div');
  ui.id = 'lab-ui';
  ui.innerHTML = `
    <div class="row">
      <div class="seg" role="group" aria-label="Version">
        <button data-mode="original">Original</button>
        <button data-mode="polished">Polished</button>
        <button data-mode="ghost">Ghost</button>
      </div>
      <button data-t="grid" class="full" title="Column grid + 8px baseline (G)">Grid</button>
      <button data-t="inspect" class="full" title="Tap any element to measure it (I)">Measure</button>
      <a class="full" href="/ui-lab/changes.html" style="padding:0 6px">Changes</a>
      <button data-t="min" title="Collapse toolbar">▾</button>
    </div>
    <div class="row ghost-row full">
      <span class="tag">ghost = original</span>
      <label>opacity <input type="range" min="0.1" max="0.9" step="0.05" data-k="opacity"></label>
      <select data-k="blend" aria-label="Blend"><option value="normal">overlay</option><option value="difference">difference</option></select>
      <select data-k="align" aria-label="Alignment"><option value="section">align by section</option><option value="page">align page top</option></select>
    </div>`;
  document.body.appendChild(ui);
  const info = document.createElement('div'); info.id = 'lab-info'; document.body.appendChild(info);

  /* ---------- ghost overlay ---------- */
  let ghost = null, ghostTimer = null;
  const topSections = doc => [...doc.querySelectorAll('section, footer, article.blog-post, .blog-section')]
    .filter(el => !el.parentElement.closest('section, article.blog-post, .blog-section, footer') && el.offsetHeight > 0);
  const absTop = (el, win) => el.getBoundingClientRect().top + win.scrollY;

  function alignGhost() {
    if (!ghost || !ghost.contentDocument || !ghost.contentDocument.body) return;
    const gd = ghost.contentDocument;
    // body height, not scrollHeight: an iframe's scrollHeight never drops below its own height
    const h = Math.ceil(gd.body.getBoundingClientRect().height);
    if (Math.abs(ghost.offsetHeight - h) > 2 && h < 60000) ghost.style.height = h + 'px';
    let offset = 0;
    if (state.align === 'section') {
      const a = topSections(document), b = topSections(gd);
      const probe = scrollY + innerHeight * 0.35;
      let i = 0;
      a.forEach((el, k) => { if (absTop(el, window) <= probe) i = k; });
      if (a[i] && b[i]) offset = absTop(a[i], window) - absTop(b[i], ghost.contentWindow);
    }
    ghost.style.transform = `translateY(${Math.round(offset)}px)`;
  }

  function setGhost(on) {
    if (on && !ghost) {
      ghost = document.createElement('iframe');
      ghost.id = 'lab-ghost';
      ghost.setAttribute('aria-hidden', 'true');
      ghost.tabIndex = -1;
      ghost.setAttribute('scrolling', 'no');
      const u = new URL(location.href); u.searchParams.set('lab', 'original'); u.hash = '';
      ghost.src = u.toString();
      ghost.style.height = innerHeight + 'px';
      ghost.addEventListener('load', () => {
        const d = ghost.contentDocument;
        d.querySelectorAll('link[rel=preload][as=style]').forEach(l => { l.rel = 'stylesheet'; });
        // Ghost shows content only: hide its fixed header/cookie bar so they don't stack at the top.
        const s = d.createElement('style');
        s.textContent = 'html{scroll-behavior:auto!important;overflow:hidden!important;scrollbar-width:none} html::-webkit-scrollbar{display:none} .header,.privacy-notice,.cookie-banner,[class*=consent]{visibility:hidden!important} *{animation:none!important;transition:none!important}';
        d.head.appendChild(s);
        setTimeout(alignGhost, 300); setTimeout(alignGhost, 1500);
      });
      document.body.appendChild(ghost);
      ghostTimer = setInterval(alignGhost, 700);
      addEventListener('scroll', alignGhost, { passive: true });
      addEventListener('resize', alignGhost);
    } else if (!on && ghost) {
      ghost.remove(); ghost = null; clearInterval(ghostTimer);
      removeEventListener('scroll', alignGhost); removeEventListener('resize', alignGhost);
    }
    if (ghost) {
      ghost.style.opacity = state.opacity;
      ghost.style.mixBlendMode = state.blend;
      alignGhost();
    }
  }

  /* ---------- grid overlay ---------- */
  let grid = null;
  function drawGrid() {
    if (!grid) return;
    const c = document.querySelector('.hero .container, main .container, .container');
    const w = innerWidth;
    const cols = w >= 969 ? 12 : w >= 600 ? 6 : 4;
    const gutter = w >= 969 ? 32 : w >= 600 ? 24 : 16;
    let left = 16, width = w - 32;
    if (c) {
      const r = c.getBoundingClientRect(), cs = getComputedStyle(c);
      left = r.left + parseFloat(cs.paddingLeft); width = r.width - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    }
    const colW = (width - gutter * (cols - 1)) / cols;
    const el = grid.querySelector('.cols');
    el.style.left = left + 'px'; el.style.width = width + 'px';
    el.style.background = `repeating-linear-gradient(to right, rgba(45,90,123,.10) 0 ${colW}px, transparent ${colW}px ${colW + gutter}px)`;
    el.style.outline = '1px solid rgba(45,90,123,.35)';
    grid.dataset.desc = `${cols} cols · ${Math.round(colW)}px col · ${gutter}px gutter · content ${Math.round(width)}px`;
  }
  function setGrid(on) {
    if (on && !grid) {
      grid = document.createElement('div'); grid.id = 'lab-grid';
      grid.innerHTML = '<div class="base"></div><div class="cols"></div>';
      document.body.appendChild(grid); addEventListener('resize', drawGrid);
    } else if (!on && grid) { grid.remove(); grid = null; removeEventListener('resize', drawGrid); }
    drawGrid();
  }

  /* ---------- inspector ---------- */
  let insp = null, picked = null;
  const r1 = v => Math.round(parseFloat(v) * 10) / 10;
  const short = el => el.tagName.toLowerCase() + (el.id ? '#' + el.id : '') + (el.classList.length ? '.' + [...el.classList].join('.') : '');
  function box(cls, x, y, w, h, label) {
    const d = document.createElement('div'); d.className = cls;
    Object.assign(d.style, { left: x + 'px', top: y + 'px', width: Math.max(0, w) + 'px', height: Math.max(0, h) + 'px' });
    if (label) d.innerHTML = `<span>${label}</span>`;
    insp.appendChild(d);
  }
  function drawInspect() {
    if (!insp) return; insp.innerHTML = '';
    if (!picked || !picked.isConnected) { info.style.display = 'none'; return; }
    const el = picked, r = el.getBoundingClientRect(), cs = getComputedStyle(el);
    const m = ['Top', 'Right', 'Bottom', 'Left'].map(s => parseFloat(cs['margin' + s]));
    const p = ['Top', 'Right', 'Bottom', 'Left'].map(s => parseFloat(cs['padding' + s]));
    const b = ['Top', 'Right', 'Bottom', 'Left'].map(s => parseFloat(cs['border' + s + 'Width']));
    box('m', r.left - m[3], r.top - m[0], r.width + m[1] + m[3], r.height + m[0] + m[2]);
    box('p', r.left + b[3], r.top + b[0], r.width - b[1] - b[3], r.height - b[0] - b[2]);
    box('c', r.left + b[3] + p[3], r.top + b[0] + p[0], r.width - b[1] - b[3] - p[1] - p[3], r.height - b[0] - b[2] - p[0] - p[2]);
    // distance to previous / next visible sibling
    const sib = (dir) => { let s = el[dir]; while (s && !(s.offsetWidth || s.offsetHeight)) s = s[dir]; return s; };
    const prev = sib('previousElementSibling'), next = sib('nextElementSibling');
    const gaps = [];
    if (prev) { const pr = prev.getBoundingClientRect(); const g = Math.round(r.top - pr.bottom);
      if (g > 0 && pr.bottom <= r.top) { box('gap', r.left, pr.bottom, r.width, g, g + 'px'); gaps.push('↑ ' + g + 'px to ' + short(prev)); } }
    if (next) { const nr = next.getBoundingClientRect(); const g = Math.round(nr.top - r.bottom);
      if (g > 0) { box('gap', r.left, r.bottom, r.width, g, g + 'px'); gaps.push('↓ ' + g + 'px to ' + short(next)); } }
    const fam = cs.fontFamily.split(',')[0].replace(/["']/g, '');
    const lh = cs.lineHeight === 'normal' ? 'normal' : r1(cs.lineHeight) + 'px (' + (parseFloat(cs.lineHeight) / parseFloat(cs.fontSize)).toFixed(2) + ')';
    info.textContent =
      `${short(el)}\n` +
      `size     ${r1(r.width)} × ${r1(r.height)}\n` +
      `font     ${fam} ${r1(cs.fontSize)}px / w${cs.fontWeight} / lh ${lh}${cs.letterSpacing !== 'normal' ? ' / ls ' + cs.letterSpacing : ''}\n` +
      `color    ${cs.color}\n` +
      (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' ? `bg       ${cs.backgroundColor}\n` : '') +
      `padding  ${p.map(Math.round).join(' ')}\n` +
      `margin   ${m.map(Math.round).join(' ')}\n` +
      (cs.borderRadius !== '0px' ? `radius   ${cs.borderRadius}\n` : '') +
      (cs.gap && cs.gap !== 'normal' ? `gap      ${cs.gap}\n` : '') +
      gaps.join('\n') + '\n(tap the same element again to select its parent)';
    info.style.display = 'block';
  }
  function onPick(e) {
    if (ui.contains(e.target) || info.contains(e.target)) return;
    e.preventDefault(); e.stopPropagation();
    picked = (picked && picked === e.target && picked.parentElement !== document.body) ? picked.parentElement : e.target;
    drawInspect();
  }
  function setInspect(on) {
    if (on && !insp) {
      insp = document.createElement('div'); insp.id = 'lab-insp'; document.body.appendChild(insp);
      document.addEventListener('click', onPick, true);
      addEventListener('scroll', drawInspect, { passive: true }); addEventListener('resize', drawInspect);
    } else if (!on && insp) {
      insp.remove(); insp = null; picked = null; info.style.display = 'none';
      document.removeEventListener('click', onPick, true);
      removeEventListener('scroll', drawInspect); removeEventListener('resize', drawInspect);
    }
  }

  /* ---------- apply state ---------- */
  function apply() {
    if (polish) polish.disabled = state.mode === 'original';
    if (window.labPolish) window.labPolish.set(state.mode !== 'original');    setGhost(state.mode === 'ghost');
    setGrid(state.grid);
    setInspect(state.inspect);
    ui.dataset.mode = state.mode; ui.dataset.min = state.min;
    ui.querySelectorAll('[data-mode]').forEach(b => b.setAttribute('aria-pressed', b.dataset.mode === state.mode));
    ui.querySelectorAll('[data-t]').forEach(b => b.setAttribute('aria-pressed', !!state[b.dataset.t] && b.dataset.t !== 'min'));
    ui.querySelector('[data-t=min]').textContent = state.min ? '▴' : '▾';
    ui.querySelector('[data-k=opacity]').value = state.opacity;
    ui.querySelector('[data-k=blend]').value = state.blend;
    ui.querySelector('[data-k=align]').value = state.align;
    const g = ui.querySelector('[data-t=grid]'); g.title = grid ? grid.dataset.desc : 'Column grid + 8px baseline (G)';
    save();
    if (polish) setTimeout(() => { drawGrid(); drawInspect(); }, 60);
  }
  ui.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.mode) state.mode = b.dataset.mode;
    if (b.dataset.t) state[b.dataset.t] = !state[b.dataset.t];
    apply();
  });
  ui.addEventListener('input', e => { const k = e.target.dataset.k; if (k) { state[k] = k === 'opacity' ? +e.target.value : e.target.value; apply(); } });
  addEventListener('keydown', e => {
    if (e.target.closest('input, textarea, select') || e.metaKey || e.ctrlKey || e.altKey) return;
    const k = e.key.toLowerCase();
    if (k === 'o') state.mode = 'original'; else if (k === 'p') state.mode = 'polished'; else if (k === 'g' && e.shiftKey) state.mode = 'ghost';
    else if (k === 'g') state.grid = !state.grid; else if (k === 'i') state.inspect = !state.inspect; else return;
    apply();
  });
  apply();
})();
