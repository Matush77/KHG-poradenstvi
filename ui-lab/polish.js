/* UI lab — polish layer, markup part. Text/markup tweaks that CSS can't do,
   applied in Polished/Ghost and reverted for Original (lab.js calls
   window.labPolish.set). Not part of the site.
   [P15 B2] contact labels: "Email:" -> "E-mail", no colons
   [P15 C2] hero stats: one quiet line "4 000+ hodin praxe · 100+ doporučení"
            (replacing the markup also stops the desktop count-up animation)
   [P16 E1] quiz start: "10 otázek" -> "10 otázek · výsledek ihned"
   [P16 F1] booking window: "ONLINE" / "OSTRAVA" label above each option title
   [P16 G2] empty blog category: full stop + "Zobrazit všechny články →"
   [P18 K1] reviews: swipe left/right on the card; auto-advance stops for good
            after the first swipe / dot tap / arrow key, and never starts with
            "reduce motion". (Uses the hidden prev/next buttons and the
            script's own hover-pause hook, so js/script.js is untouched.)
   [P19] hero blob: pause its endless animation while it is off-screen
   [P21] services CTA: specific copy; Czech non-breaking space after
         one-letter words (v, k, s, z, o, u, a, i) in the page text */
(function () {
  'use strict';
  const saved = new Map();
  const swap = (el, html) => { if (!el) return; if (!saved.has(el)) saved.set(el, el.innerHTML); el.innerHTML = html; };
  const czNum = n => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');   // 4000 -> "4 000" (non-breaking)
  const added = [];
  const texts = new Map();   // P21: text nodes changed by the Czech-spacing pass
  const add = (parent, before, html) => {
    if (!parent) return; const t = document.createElement('template'); t.innerHTML = html.trim();
    const el = t.content.firstChild; parent.insertBefore(el, before || null); added.push(el);
  };
  let on = false;

  document.addEventListener('click', e => {
    if (!on || !e.target.closest('.lab-empty-link')) return;
    e.preventDefault(); const all = document.querySelector('.category-btn[data-category="all"]'); if (all) all.click();
  });

  // ---- P18 K1: swipe + stop auto-advance (listeners act only while Polished) ----
  (function reviews() {
    const track = document.querySelector('.testimonials-track');
    const carousel = track && track.closest('.testimonials-carousel');
    if (!track || !carousel) return;
    let stopped = false, x0 = null, y0 = 0;
    const stop = () => { if (stopped) return; stopped = true; track.dispatchEvent(new MouseEvent('mouseenter')); };
    // script.js restarts auto-advance on mouseleave; block that once the visitor took over
    carousel.addEventListener('mouseleave', e => { if (on && stopped && e.target === track) e.stopImmediatePropagation(); }, true);
    carousel.addEventListener('click', e => { if (on && e.target.closest('.carousel-dot')) stop(); });
    carousel.addEventListener('keydown', e => { if (on && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) stop(); });
    track.addEventListener('touchstart', e => { if (!on) return; x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    track.addEventListener('touchend', e => {
      if (!on || x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0; x0 = null;
      if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
      stop();
      const btn = carousel.querySelector(dx < 0 ? '.carousel-btn-next' : '.carousel-btn-prev');
      if (btn) btn.click();
    }, { passive: true });
    // after script.js has started its timer (it does so on DOMContentLoaded)
    const later = fn => document.readyState === 'loading'
      ? document.addEventListener('DOMContentLoaded', () => setTimeout(fn, 0)) : fn();
    window.labReviewsReducedMotion = () => later(() => { if (on && matchMedia('(prefers-reduced-motion: reduce)').matches) stop(); });
  })();

  // ---- P19: pause the hero blob while it can't be seen ----
  (function blob() {
    const el = document.querySelector('.blob-shape');
    if (!el || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(([e]) => el.classList.toggle('lab-offscreen', !e.isIntersecting)).observe(el);
  })();

  function apply() {
    document.querySelectorAll('.contact-label').forEach(el => {
      swap(el, el.textContent.replace(/:\s*$/, '').replace(/^Email$/i, 'E-mail'));
    });
    const stats = document.querySelector('.hero-stats');
    if (stats) {
      const parts = [...stats.querySelectorAll('.hero-stat-text')].map(el => {
        const m = el.textContent.trim().match(/^\+?\s*(\d+)\s*\+?\s*(.*)$/);
        return m ? `<strong>${czNum(m[1])}+</strong> ${m[2]}` : el.textContent.trim();
      });
      if (parts.length) swap(stats, `<p class="lab-stats-line">${parts.join('<span class="lab-dot" aria-hidden="true">·</span>')}</p>`);
    }
    document.querySelectorAll('.quiz-info-box span').forEach(el => {
      const m = el.textContent.trim().match(/^(\d+)\s+(.*)$/);
      if (m) swap(el, `<strong>${m[1]}</strong>\u00a0${m[2]}<span class="lab-dot" aria-hidden="true">·</span>výsledek ihned`);
    });
    document.querySelectorAll('.booking-option').forEach(opt => {
      const title = opt.querySelector('.booking-option-title'); if (!title) return;
      const label = /online/i.test(title.textContent) ? 'Online' : 'Ostrava';
      add(opt, title, `<span class="lab-option-label">${label}</span>`);
    });
    const empty = document.querySelector('#emptyState p');
    if (empty) {
      swap(empty, empty.textContent.trim().replace(/\.?$/, '.'));
      add(empty.parentNode, null, '<a href="#" class="lab-empty-link">Zobrazit všechny články →</a>');
    }
    // ---- P21: specific CTA copy instead of "Připraveni začít?" (facts from the
    //      site's own "Když si nejste jistí" text) ----
    const cta = document.querySelector('.services .cta-section');
    if (cta) {
      swap(cta.querySelector('.cta-heading'), 'Stačí jedno první setkání');
      swap(cta.querySelector('.cta-heading + p'),
        'Nemusíte se předem rozhodovat pro dlouhodobou terapii. Na prvním setkání společně zmapujeme, co řešíte, a zjistíte, jestli vám spolupráce sedí.');
    }
    // ---- P21: Czech typesetting: no one-letter word (v, k, s, z, o, u, a, i)
    //      left at a line end; glue it to the next word ----
    const walker = document.createTreeWalker(document.querySelector('main') || document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: n => n.parentElement.closest('script, style, #lab-ui') ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT });
    const re = /(^|[\s(„])([vkszouaiVKSZOUAI]) +/g;
    for (let n; (n = walker.nextNode());) {
      if (!re.test(n.nodeValue)) continue; re.lastIndex = 0;
      texts.set(n, n.nodeValue); n.nodeValue = n.nodeValue.replace(re, '$1$2 ');
    }
    if (window.labReviewsReducedMotion) window.labReviewsReducedMotion();
    document.documentElement.classList.add('lab-markup');
  }

  function revert() {
    saved.forEach((html, el) => { el.innerHTML = html; });
    saved.clear();
    texts.forEach((v, n) => { n.nodeValue = v; }); texts.clear();
    added.forEach(el => el.remove()); added.length = 0;
    document.documentElement.classList.remove('lab-markup');
  }

  window.labPolish = { set(v) { if (v === on) return; on = v; v ? apply() : revert(); } };
})();
