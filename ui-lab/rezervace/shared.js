/* Shared data and steps 2-3 for the five booking variants (lab only). Each variant page supplies pick(view). */
window.KHG = (function () {
  var MONTHS = ['Leden','Únor','Březen','Duben','Květen','Červen','Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
  var MONTHS_GEN = ['ledna','února','března','dubna','května','června','července','srpna','září','října','listopadu','prosince'];
  var DAYS = ['neděle','pondělí','úterý','středa','čtvrtek','pátek','sobota'];
  var DAYS_SHORT = ['Ne','Po','Út','St','Čt','Pá','So'];
  var DOW = ['Po','Út','St','Čt','Pá','So','Ne'];
  /* Real free times copied from the Google booking pages (availability.js). */
  var AV = window.KHG_AVAIL;
  var TODAY = new Date(2026, 10, 13);
  var FIRST = TODAY;
  var LAST = new Date(2027, 0, 31);
  var FORMATS = {
    online: { name: 'Online', where: 'videohovor Google&nbsp;Meet', facts: '<b>50 minut · 1 100 Kč</b> · videohovor Google&nbsp;Meet', after: 'Odkaz na videohovor je v potvrzení.' },
    osobne: { name: 'Osobně v Ostravě', where: 'Vítkovická 3299/3A, Ostrava', facts: '<b>50 minut · 1 100 Kč</b> · Vítkovická 3299/3A, Ostrava', after: 'Ordinace je na adrese Vítkovická 3299/3A, Ostrava. Prosím, zvoňte až v čase vašeho sezení.' }
  };

  var state = { f: 'online', date: null, time: null, step: 1, email: '' };
  var view, opts, bar;

  function key(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function same(a, b) { return !!a && !!b && key(a) === key(b); }
  function addDays(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
  function slotsFor(d, f) { return (AV[f || state.f][key(d)] || []).slice(); }
  function long(d) { return DAYS[d.getDay()] + ' ' + d.getDate() + '. ' + MONTHS_GEN[d.getMonth()]; }
  function short(d) { return DAYS_SHORT[d.getDay()] + ' ' + d.getDate() + '. ' + (d.getMonth() + 1) + '.'; }
  function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }
  function rel(d) {
    var n = Math.round((d - TODAY) / 864e5);
    if (n === 1) return 'zítra'; if (n === 2) return 'pozítří';
    if (n < 5) return 'za ' + n + ' dny';
    return 'za ' + n + ' dní';
  }
  function firstFree(from) {
    for (var d = new Date(Math.max(from || TODAY, FIRST)); d <= LAST; d = addDays(d, 1)) if (slotsFor(d).length) return d;
    return null;
  }
  function freeDays(from, count) {
    var out = [];
    for (var d = new Date(Math.max(from || TODAY, FIRST)); d <= LAST && out.length < count; d = addDays(d, 1)) if (slotsFor(d).length) out.push(d);
    return out;
  }
  function endTime(t) { var m = +t.split(':')[0] * 60 + +t.split(':')[1] + 50; return Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0'); }

  function formatSwitch() {
    return '<div class="switch" role="group" aria-label="Forma sezení">' +
      ['online', 'osobne'].map(function (f) {
        return '<button class="fmt" data-f="' + f + '" aria-pressed="' + (state.f === f) + '">' + (f === 'online' ? 'Online' : 'Osobně') + '</button>';
      }).join('') + '</div>';
  }
  /* Changing the format keeps the day when it still has times, else jumps to the first free day (pass keepEmpty to clear instead). */
  function bindSwitch(keepEmpty) {
    view.querySelectorAll('.fmt').forEach(function (b) {
      b.onclick = function () { setFormat(b.dataset.f, keepEmpty); };
    });
  }
  function setFormat(f, keepEmpty) {
    state.f = f; state.time = null;
    if (!state.date || !slotsFor(state.date).length) state.date = keepEmpty ? null : firstFree(TODAY);
    render();
  }

  /* Month grid. Returns html; free days are buttons with data-d="yyyy-mm-dd". extra(d, cellIndex) can add cells after a week row. */
  function monthGrid(month, after) {
    var y = month.getFullYear(), mo = month.getMonth();
    var start = (new Date(y, mo, 1).getDay() + 6) % 7, len = new Date(y, mo + 1, 0).getDate();
    var cells = DOW.map(function (d) { return '<div class="dow" aria-hidden="true">' + d + '</div>'; });
    var col = 0;
    for (var i = 0; i < start; i++) { cells.push('<span class="day out"></span>'); col++; }
    for (var n = 1; n <= len; n++) {
      var d = new Date(y, mo, n), free = slotsFor(d).length > 0;
      var cls = 'day' + (free ? ' free' : '') + (same(d, state.date) ? ' sel' : '');
      cells.push(free
        ? '<button class="' + cls + '" data-d="' + key(d) + '" aria-label="' + long(d) + ', volné termíny" aria-pressed="' + same(d, state.date) + '">' + n + '</button>'
        : '<span class="' + cls + '" aria-hidden="true">' + n + '</span>');
      col++;
      if (after && (col % 7 === 0 || n === len)) {
        var weekStart = addDays(d, -((col - 1) % 7));
        cells.push(after(weekStart, d));
      }
    }
    return '<div class="grid">' + cells.join('') + '</div>';
  }
  function monthNav(month) {
    var mo = month.getMonth(), m = month.getFullYear() * 12 + mo;
    return '<div class="cal-top"><button class="nav" data-m="-1" aria-label="Předchozí měsíc"' + (m <= FIRST.getFullYear() * 12 + FIRST.getMonth() ? ' disabled' : '') + '>‹</button>' +
      '<b>' + MONTHS[mo] + ' ' + month.getFullYear() + '</b>' +
      '<button class="nav" data-m="1" aria-label="Další měsíc"' + (m >= LAST.getFullYear() * 12 + LAST.getMonth() ? ' disabled' : '') + '>›</button></div>';
  }
  function parse(k) { var p = k.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

  function summary() {
    return '<div class="summary"><b>' + cap(long(state.date)) + ', ' + state.time + '–' + endTime(state.time) + '</b><span>' + FORMATS[state.f].name + ' · 50 minut · 1 100 Kč</span></div>';
  }

  /* Fixed bar showing the choice (variants that set opts.bar). */
  function renderBar() {
    if (!bar) return;
    var on = state.step === 1 && state.date && state.time;
    bar.classList.toggle('on', !!on);
    bar.setAttribute('aria-hidden', on ? 'false' : 'true');
    if (on) {
      bar.querySelector('b').textContent = short(state.date) + ' v ' + state.time;
      bar.querySelector('span').innerHTML = FORMATS[state.f].name + ' · 50 minut · 1 100 Kč';
    }
    bar.querySelector('button').tabIndex = on ? 0 : -1;
  }

  /* Details. Online asks only for an e-mail (the Meet link goes there), in person only for a phone. Last, an optional free-text reason. */
  function field(id, label, type, ac, required, value, ph) {
    return '<label for="' + id + '"><span>' + label + (required ? '' : ' <em>(nepovinné)</em>') + '</span>' +
      '<input id="' + id + '" type="' + type + '" autocomplete="' + ac + '"' + (required ? ' required aria-required="true"' : '') +
      (value ? ' value="' + value + '"' : '') + (ph ? ' placeholder="' + ph + '"' : '') + ' aria-describedby="' + id + '-err">' +
      '<span class="err" id="' + id + '-err" aria-live="polite"></span></label>';
  }
  function renderStep2() {
    var online = state.f === 'online';
    var contact = online
      ? field('e', 'E-mail', 'email', 'email', true, 'jana.novakova@example.cz', '')
      : field('p', 'Telefon', 'tel', 'tel', true, '+420 777 123 456', '+420');
    var reason = '<label for="r"><span>S čím přicházíte <em>(nepovinné)</em></span><textarea id="r" rows="3"></textarea></label>';
    view.innerHTML =
      (opts.kicker ? opts.kicker(2) : '') +
      '<button class="back" id="back">‹ Změnit termín</button>' +
      '<h1>Vaše údaje</h1>' + summary() +
      '<form id="f" novalidate>' +
      '<div class="names">' + field('fn', 'Jméno', 'text', 'given-name', true, 'Jana', '') + field('ln', 'Příjmení', 'text', 'family-name', true, 'Nováková', '') + '</div>' +
      contact + reason +
      '<div class="cta"><button class="btn" type="submit">Zaplatit 1 100 Kč</button><span class="pay">Kartou, Apple Pay nebo Google Pay. Zrušení zdarma nejpozději 36 hodin předem.</span></div>' +
      '</form>';
    document.getElementById('back').onclick = function () { go(1); };
    document.getElementById('f').onsubmit = function (ev) {
      ev.preventDefault();
      var checks = {
        fn: function (v) { return v ? '' : 'Vyplňte prosím jméno.'; },
        ln: function (v) { return v ? '' : 'Vyplňte prosím příjmení.'; },
        e: function (v, req) { return !v ? (req ? 'Vyplňte prosím e-mail, pošlu na něj odkaz na videohovor.' : '') : (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'E-mail nevypadá správně.'); },
        p: function (v, req) { return !v ? (req ? 'Vyplňte prosím telefon.' : '') : (v.replace(/\D/g, '').length >= 9 ? '' : 'Telefon nevypadá správně.'); }
      };
      var first = null;
      Object.keys(checks).forEach(function (id) {
        var el = document.getElementById(id); if (!el) return;
        var msg = checks[id](el.value.trim(), el.required);
        el.setAttribute('aria-invalid', msg ? 'true' : 'false');
        document.getElementById(id + '-err').textContent = msg;
        if (msg && !first) first = el;
      });
      if (first) { first.focus(); return; }
      state.email = online ? document.getElementById('e').value.trim() : '';
      state.phone = online ? '' : document.getElementById('p').value.trim();
      go(3);
    };
  }
  function renderStep3() {
    var sent = state.email ? 'Potvrzení přišlo na ' + state.email + '. ' : 'Termín máte zapsaný na telefon ' + state.phone + '. ';
    view.innerHTML = '<h1>Termín je rezervovaný</h1>' + summary() +
      '<p class="facts">' + sent + FORMATS[state.f].after + '</p>' +
      '<p class="facts">Zrušit zdarma můžete nejpozději 36 hodin před sezením, vrátíme celou částku.</p>' +
      '<div><button class="btn" id="again">Zpět na začátek</button></div>';
    document.getElementById('again').onclick = function () { state.time = null; if (opts.reset) opts.reset(); go(1); };
  }

  function render() {
    document.body.dataset.f = state.f || '';
    if (state.step === 1) opts.pick(view); else if (state.step === 2) renderStep2(); else renderStep3();
    renderBar();
  }
  function go(step) {
    state.step = step; render();
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var top = document.querySelector('.panel').getBoundingClientRect().top;
    if (top < 0) window.scrollTo({ top: scrollY + top - 16, behavior: reduce ? 'auto' : 'smooth' });
    var h = view.querySelector('h1'); if (h && step > 1) { h.tabIndex = -1; h.focus({ preventScroll: true }); }
  }
  function scrollTo(el) {
    if (!el) return;
    var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    var r = el.getBoundingClientRect();
    if (r.bottom > innerHeight - 130 || r.top < 0) el.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
  }

  function start(o) {
    opts = o; view = document.getElementById('view');
    if (location.hash === '#embed') document.querySelectorAll('.lab, .lab-what').forEach(function (el) { el.style.display = 'none'; document.body.style.paddingTop = '16px'; });
    if (o.bar) {
      document.body.setAttribute('data-bar', '');
      bar = document.createElement('div');
      bar.className = 'bar'; bar.setAttribute('aria-hidden', 'true');
      bar.innerHTML = '<div class="bar-in"><div><b></b><span></span></div><button class="btn" tabindex="-1">Pokračovat</button></div>';
      bar.querySelector('button').onclick = function () { go(2); };
      document.body.appendChild(bar);
    }
    if (o.init) o.init();
    render();
  }

  return {
    state: state, FORMATS: FORMATS, TODAY: TODAY, FIRST: FIRST, LAST: LAST, MONTHS: MONTHS, MONTHS_GEN: MONTHS_GEN, DAYS_SHORT: DAYS_SHORT,
    key: key, same: same, parse: parse, addDays: addDays, slotsFor: slotsFor, long: long, short: short, cap: cap, rel: rel,
    firstFree: firstFree, freeDays: freeDays, endTime: endTime,
    formatSwitch: formatSwitch, bindSwitch: bindSwitch, setFormat: setFormat, monthGrid: monthGrid, monthNav: monthNav,
    render: render, go: go, scrollTo: scrollTo, start: start
  };
})();
