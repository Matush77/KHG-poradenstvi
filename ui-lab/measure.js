// Spacing/structure inventory: per section, its padding, header gap, and every boxed part's padding, gap and spacing to neighbours.
// Run in the page: eval(await (await fetch('/ui-lab/measure.js')).text())
(() => {
  const px = v => Math.round(parseFloat(v) || 0);
  const vis = e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(e).display !== 'none'; };
  const name = e => e.tagName.toLowerCase() + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).join('.') : '');
  const boxed = e => { const s = getComputedStyle(e); return s.backgroundColor !== 'rgba(0, 0, 0, 0)' || (s.borderTopWidth !== '0px' && s.borderTopStyle !== 'none') || (s.borderBottomWidth !== '0px' && s.borderBottomStyle !== 'none') || px(s.paddingTop) + px(s.paddingLeft) > 0 && e.children.length > 1; };
  const out = [];
  document.querySelectorAll('main > section, main > .section, body > section, footer, .rv').forEach(sec => {
    if (!vis(sec)) return;
    const s = getComputedStyle(sec), r = sec.getBoundingClientRect();
    const head = sec.querySelector('.section-header, .rv-head, h2');
    const headR = head && head.getBoundingClientRect();
    const afterHead = head && head.nextElementSibling ? Math.round(head.nextElementSibling.getBoundingClientRect().top - headR.bottom) : null;
    const cont = sec.querySelector('.container');
    const cs = cont && getComputedStyle(cont);
    const parts = [];
    sec.querySelectorAll('*').forEach(e => {
      if (!vis(e) || e.closest('.rv-track') && !e.matches('.rv-card') || e.matches('a,span,strong,em,picture,img,svg,path,br,li,dt,dd,button,p,h1,h2,h3,h4,figcaption') || !boxed(e)) return;
      const es = getComputedStyle(e), er = e.getBoundingClientRect();
      const next = e.nextElementSibling && vis(e.nextElementSibling) ? Math.round(e.nextElementSibling.getBoundingClientRect().top - er.bottom) : null;
      parts.push(`${name(e)} ${Math.round(er.width)}w pad ${px(es.paddingTop)}/${px(es.paddingRight)}/${px(es.paddingBottom)}/${px(es.paddingLeft)} gap ${es.display.includes('grid') || es.display.includes('flex') ? es.rowGap + '|' + es.columnGap : '-'} r${es.borderRadius} bg ${es.backgroundColor.replace('rgba(0, 0, 0, 0)', '-')} →next ${next}`);
    });
    out.push({ section: name(sec), h: Math.round(r.height), pad: `${px(s.paddingTop)}/${px(s.paddingBottom)}`, bg: s.backgroundColor, container: cs ? `${Math.round(cont.getBoundingClientRect().width)}w padX ${px(cs.paddingLeft)}` : '-', headToContent: afterHead, parts: parts.slice(0, 25) });
  });
  return { w: innerWidth, out };
})()
