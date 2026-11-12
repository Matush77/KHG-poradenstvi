(() => {
  const vis = e => { const r = e.getBoundingClientRect(); const s = getComputedStyle(e); return r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none'; };
  const els = [...document.querySelectorAll('body *')].filter(e => !e.closest('.booking-modal, script, style, svg, noscript') && vis(e));
  const own = e => [...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1);
  const tally = (arr) => { const m = {}; arr.forEach(([k, ex]) => { m[k] = m[k] || { n: 0, ex }; m[k].n++; }); return Object.entries(m).sort((a, b) => b[1].n - a[1].n).map(([k, v]) => `${v.n}× ${k}  [${v.ex}]`); };
  const lab = e => (e.tagName.toLowerCase() + (e.className && typeof e.className === 'string' ? '.' + e.className.trim().split(/\s+/)[0] : ''));
  const text = els.filter(own);
  const fam = s => s.fontFamily.split(',')[0].replace(/"/g, '');
  const type = tally(text.map(e => { const s = getComputedStyle(e); return [`${fam(s)} ${s.fontSize} ${s.fontWeight} lh${(parseFloat(s.lineHeight) / parseFloat(s.fontSize) || 0).toFixed(2)} ${s.fontStyle === 'italic' ? 'italic ' : ''}${s.textTransform === 'uppercase' ? 'UPPER ' + s.letterSpacing : ''}`, lab(e)]; }));
  const color = tally(text.map(e => [getComputedStyle(e).color, lab(e)]));
  const bg = tally(els.map(e => [getComputedStyle(e).backgroundColor, lab(e)]).filter(([c]) => c !== 'rgba(0, 0, 0, 0)'));
  const grad = tally(els.map(e => [getComputedStyle(e).backgroundImage, lab(e)]).filter(([c]) => c.includes('gradient')).map(([c, l]) => [c.slice(0, 90), l]));
  const radius = tally(els.map(e => [getComputedStyle(e).borderRadius, lab(e)]).filter(([r]) => r !== '0px'));
  const shadow = tally(els.map(e => [getComputedStyle(e).boxShadow, lab(e)]).filter(([r]) => r !== 'none').map(([c, l]) => [c.slice(0, 80), l]));
  const border = tally(els.map(e => { const s = getComputedStyle(e); return [s.borderTopWidth !== '0px' && s.borderTopStyle !== 'none' ? `${s.borderTopWidth} ${s.borderTopColor}` : (s.borderLeftWidth !== '0px' && s.borderLeftStyle !== 'none' ? `L ${s.borderLeftWidth} ${s.borderLeftColor}` : ''), lab(e)]; }).filter(([b]) => b));
  const btn = tally([...document.querySelectorAll('.btn, button[class*=btn], a[class*=btn], [class*=button]')].filter(vis).map(e => { const s = getComputedStyle(e); const r = e.getBoundingClientRect(); return [`${Math.round(r.height)}h bg ${s.backgroundColor} fg ${s.color} ${s.fontSize}/${s.fontWeight} r${s.borderRadius}`, lab(e) + ':' + e.textContent.trim().slice(0, 20)]; }));
  const sections = [...document.querySelectorAll('section, header, footer')].filter(e=>!e.parentElement.closest('section')).filter(vis).map(e => `${lab(e)} ${getComputedStyle(e).backgroundColor} ${getComputedStyle(e).backgroundImage.slice(0, 50)} pad ${getComputedStyle(e).paddingTop}`);
  const pseudo = [];
  els.forEach(e => ['::before', '::after'].forEach(p => { const s = getComputedStyle(e, p); if (s.content !== 'none' && s.content !== 'normal' && s.display !== 'none' && (s.backgroundImage !== 'none' || s.backgroundColor !== 'rgba(0, 0, 0, 0)')) pseudo.push([`${p} ${s.backgroundImage !== 'none' ? s.backgroundImage.slice(0, 60) : s.backgroundColor}`, lab(e)]); }));
  const pseudoDeco = tally(pseudo);
  return { pseudoDeco, url: location.pathname, w: innerWidth, type, color, bg, grad, radius, shadow, border, btn, sections, fontsLoaded: [...document.fonts].filter(f => f.status === 'loaded').map(f => `${f.family} ${f.weight} ${f.style}`) };
})()
