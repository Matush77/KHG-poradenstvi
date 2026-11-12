// Paste into the pane after a fresh load at 375 px: CLS over load + full scroll, LCP element/time, requests and bytes, page height.
(async () => {
  let cls = 0; const shifts = [];
  new PerformanceObserver(l => l.getEntries().forEach(e => { if (!e.hadRecentInput) { cls += e.value; shifts.push(e.sources?.map(s => s.node?.className || s.node?.nodeName).join(',')); } })).observe({ type: 'layout-shift', buffered: true });
  let lcp = null;
  new PerformanceObserver(l => { const e = l.getEntries().at(-1); lcp = { t: Math.round(e.startTime), el: e.element ? (e.element.id || e.element.className || e.element.nodeName) : e.url }; }).observe({ type: 'largest-contentful-paint', buffered: true });
  const h = document.documentElement.scrollHeight;
  for (let y = 0; y <= h; y += 400) { scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); }
  await new Promise(r => setTimeout(r, 400)); scrollTo(0, 0);
  const res = performance.getEntriesByType('resource');
  return JSON.stringify({ cls: +cls.toFixed(4), shifts, lcp, requests: res.length + 1, kB: Math.round((res.reduce((a, r) => a + (r.transferSize || r.encodedBodySize || 0), 0) + (performance.getEntriesByType('navigation')[0]?.encodedBodySize || 0)) / 1024), height: h });
})()
