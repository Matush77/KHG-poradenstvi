/* Variant B only, phones: long reviews are shortened with a button that opens
   the full text, and the review shows it can be swiped by moving: it nudges
   sideways when the section comes into view and follows the finger. */
(function () {
    var track = document.querySelector('.k-quotes .testimonials-track');
    if (!track) return;
    var cards = Array.prototype.slice.call(track.querySelectorAll('.testimonial-card'));
    var phone = window.matchMedia('(max-width: 768px)');
    var touched = false;

    function addButtons() {
        cards.forEach(function (card) {
            var text = card.querySelector('.testimonial-text');
            var has = card.querySelector('.k-qmore');
            if (!phone.matches) { if (has) has.remove(); card.classList.remove('is-open'); return; }
            if (has || text.scrollHeight <= text.clientHeight + 4) return;
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'k-qmore';
            button.textContent = 'Celá recenze';
            button.setAttribute('aria-expanded', 'false');
            button.addEventListener('click', function () {
                var open = card.classList.toggle('is-open');
                button.textContent = open ? 'Zkrátit' : 'Celá recenze';
                button.setAttribute('aria-expanded', String(open));
            });
            text.insertAdjacentElement('afterend', button);
        });
    }
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(addButtons);
    window.addEventListener('load', addButtons);
    phone.addEventListener('change', addButtons);
    addButtons();

    /* nudge: one small move, on at most two visits, each time the reviews scroll into view, until the visitor swipes */
    var nudges = 0;
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { track.classList.remove('k-nudge'); return; }
                if (touched || nudges >= 2 || !phone.matches) return;
                nudges += 1;
                track.classList.add('k-nudge');
            });
        }, { threshold: 0.6 });
        io.observe(track);
    }

    /* drag: the text follows the finger; script.js changes the review on release */
    var startX = null, startY = 0, dragging = false;
    function active() { return track.querySelector('.testimonial-card.active'); }
    function release() {
        cards.forEach(function (card) {
            card.classList.remove('k-drag');
            card.classList.add('k-settle');
            card.style.transform = '';
            card.style.opacity = '';
        });
        setTimeout(function () { cards.forEach(function (card) { card.classList.remove('k-settle'); }); }, 320);
        startX = null; dragging = false;
    }
    track.addEventListener('touchstart', function (e) {
        if (!phone.matches) return;
        touched = true;
        track.classList.remove('k-nudge');
        startX = e.touches[0].clientX; startY = e.touches[0].clientY; dragging = false;
    }, { passive: true });
    track.addEventListener('touchmove', function (e) {
        if (startX === null) return;
        var dx = e.touches[0].clientX - startX, dy = e.touches[0].clientY - startY;
        if (!dragging && Math.abs(dx) < 8) return;
        if (!dragging && Math.abs(dx) < Math.abs(dy)) { startX = null; return; }
        dragging = true;
        var card = active();
        if (!card) return;
        card.classList.add('k-drag');
        card.style.transform = 'translateX(' + (dx * 0.6) + 'px)';
        card.style.opacity = String(Math.max(0.35, 1 - Math.abs(dx) / 260));
    }, { passive: true });
    track.addEventListener('touchend', release, { passive: true });
    track.addEventListener('touchcancel', release, { passive: true });
})();
