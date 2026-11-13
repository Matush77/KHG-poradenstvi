/* Puts the real site header (from the lab redesign, ui-lab/proposal/cenik.html) on a booking preview.
   The header and the site's stylesheets live in a shadow root, so the site CSS and the preview CSS cannot touch each other.
   Space for the fixed header is reserved by .site-header-host before anything loads (no layout shift). */
(function () {
  var host = document.createElement('div');
  host.className = 'site-header-host';
  document.body.insertBefore(host, document.body.firstChild);
  var root = host.attachShadow({ mode: 'open' });

  // site pages call this from the "Objednat se" button; here the booking is already on the page
  window.openBookingModal = function () {
    var p = document.querySelector('.panel');
    if (p) p.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  };

  function scope(css) {
    // :root, html and body rules of the site apply to the shadow host instead
    return css.replace(/:root\b/g, ':host').replace(/(^|[\s,{}>])(html|body)(?=[\s,{.:\[>])/g, '$1:host');
  }

  fetch('/ui-lab/proposal/cenik.html').then(function (r) { return r.text(); }).then(function (html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var header = doc.querySelector('header.header');
    var inline = Array.prototype.map.call(doc.querySelectorAll('head style'), function (s) { return s.textContent; }).join('\n');
    return Promise.all(['/ui-lab/proposal/styles.css?v=lab1', '/css/polish.css?v=20261111-3'].map(function (u) {
      return fetch(u).then(function (r) { return r.ok ? r.text() : ''; });
    })).then(function (sheets) {
      var style = document.createElement('style');
      style.textContent = scope(sheets.join('\n') + '\n' + inline) +
        '\n:host { display: block; padding: 0 !important; margin: 0 !important; min-height: 0 !important; background: none !important; }';
      root.appendChild(style);
      root.appendChild(document.importNode(header, true));
      wireMenu();
    });
  }).catch(function () { host.remove(); document.body.classList.add('no-site-header'); });

  // same behaviour as the site's js/script.js
  function wireMenu() {
    var toggle = root.querySelector('.nav-toggle'), menu = root.querySelector('.nav-menu');
    if (!toggle || !menu) return;
    var lines = toggle.querySelectorAll('span');
    function set(open) {
      menu.classList.toggle('active', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Zavřít navigaci' : 'Otevřít navigaci');
      lines[0].style.transform = open ? 'rotate(45deg) translate(5px, 5px)' : 'none';
      lines[1].style.opacity = open ? '0' : '1';
      lines[2].style.transform = open ? 'rotate(-45deg) translate(7px, -6px)' : 'none';
    }
    set(false);
    toggle.addEventListener('click', function () { set(!menu.classList.contains('active')); });
    menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
    document.addEventListener('click', function (e) { if (menu.classList.contains('active') && !e.composedPath().includes(host)) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu.classList.contains('active')) { set(false); toggle.focus(); } });
  }
})();
