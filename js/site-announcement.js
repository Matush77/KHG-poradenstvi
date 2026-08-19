(function () {
    'use strict';

    const STORAGE_KEY = 'khg_price_notice_20260901_v1';
    const NOTICE_LIFETIME_MS = 365 * 24 * 60 * 60 * 1000;
    const INITIAL_DELAY_MS = 700;
    const AFTER_COOKIE_DELAY_MS = 350;
    const FOCUSABLE_SELECTOR = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ].join(',');

    let overlay = null;
    let cookieObserver = null;
    let showTimer = null;
    let previouslyFocused = null;
    let previousBodyOverflow = '';
    let backgroundStates = [];

    function noticeWasDismissed() {
        try {
            const stored = JSON.parse(localStorage.getItem(STORAGE_KEY));
            const isCurrent = stored &&
                Number.isFinite(stored.dismissedAt) &&
                Date.now() - stored.dismissedAt < NOTICE_LIFETIME_MS;

            if (isCurrent) return true;
            localStorage.removeItem(STORAGE_KEY);
        } catch (error) {
            // Bez localStorage se oznámení zobrazí znovu při příští návštěvě.
        }

        return false;
    }

    function rememberDismissal() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
        } catch (error) {
            // Zavření funguje i bez trvalého uložení volby.
        }
    }

    function setBackgroundInactive() {
        backgroundStates = [];

        Array.from(document.body.children).forEach(function (element) {
            if (element === overlay || element.tagName === 'SCRIPT') return;

            backgroundStates.push({
                element: element,
                wasInert: element.hasAttribute('inert')
            });
            element.setAttribute('inert', '');
        });
    }

    function restoreBackground() {
        backgroundStates.forEach(function (state) {
            if (!state.wasInert) state.element.removeAttribute('inert');
        });
        backgroundStates = [];
    }

    function getFocusableElements() {
        if (!overlay) return [];
        return Array.from(overlay.querySelectorAll(FOCUSABLE_SELECTOR)).filter(function (element) {
            return !element.hasAttribute('hidden') && element.getAttribute('aria-hidden') !== 'true';
        });
    }

    function closeAnnouncement() {
        if (!overlay || overlay.dataset.closing === 'true') return;

        overlay.dataset.closing = 'true';
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        rememberDismissal();

        const closingOverlay = overlay;
        const focusTarget = previouslyFocused;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        window.setTimeout(function () {
            closingOverlay.remove();
            if (overlay === closingOverlay) overlay = null;
            document.body.style.overflow = previousBodyOverflow;
            restoreBackground();

            if (focusTarget && document.contains(focusTarget)) {
                focusTarget.focus({ preventScroll: true });
            }
        }, reducedMotion ? 0 : 220);
    }

    function handleKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeAnnouncement();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = getFocusableElements();
        if (!focusable.length) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function createAnnouncement() {
        const element = document.createElement('div');
        element.className = 'site-announcement-overlay';
        element.setAttribute('aria-hidden', 'true');
        element.innerHTML =
            '<section class="site-announcement-dialog" role="dialog" aria-modal="true" aria-labelledby="site-announcement-title" aria-describedby="site-announcement-description">' +
                '<button type="button" class="site-announcement-close" data-announcement-close aria-label="Zavřít oznámení">' +
                    '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"></path></svg>' +
                '</button>' +
                '<p class="site-announcement-label">Aktualizace ceníku</p>' +
                '<h2 id="site-announcement-title">Důležitá informace pro nové i stávající klienty</h2>' +
                '<div id="site-announcement-description" class="site-announcement-copy">' +
                    '<p class="site-announcement-price">Od <time datetime="2026-09-01">1. 9. 2026</time> se cena individuální terapie upravuje na <strong>1&nbsp;100&nbsp;Kč za sezení</strong>.</p>' +
                    '<p>Snažím se cenu terapie i nadále udržovat dostupnou, zároveň je však potřeba, aby reflektovala rostoucí náklady na provoz praxe a průběžné odborné vzdělávání.</p>' +
                    '<p>Děkuji za pochopení a vaši důvěru.</p>' +
                '</div>' +
                '<div class="site-announcement-actions">' +
                    '<button type="button" class="btn btn-primary site-announcement-confirm" data-announcement-close>Rozumím</button>' +
                '</div>' +
            '</section>';

        element.addEventListener('click', function (event) {
            if (event.target === element || event.target.closest('[data-announcement-close]')) {
                closeAnnouncement();
            }
        });
        element.addEventListener('keydown', handleKeydown);

        return element;
    }

    function waitForCookieNotice() {
        if (cookieObserver) return;

        cookieObserver = new MutationObserver(function () {
            if (document.getElementById('cookie-consent')) return;

            cookieObserver.disconnect();
            cookieObserver = null;
            showTimer = window.setTimeout(openAnnouncement, AFTER_COOKIE_DELAY_MS);
        });
        cookieObserver.observe(document.body, { childList: true });
    }

    function openAnnouncement() {
        showTimer = null;

        if (overlay || noticeWasDismissed()) return;
        if (document.getElementById('cookie-consent')) {
            waitForCookieNotice();
            return;
        }

        previouslyFocused = document.activeElement;
        previousBodyOverflow = document.body.style.overflow;
        overlay = createAnnouncement();
        document.body.appendChild(overlay);
        setBackgroundInactive();
        document.body.style.overflow = 'hidden';

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                if (!overlay) return;
                overlay.setAttribute('aria-hidden', 'false');
                overlay.classList.add('is-visible');
                const closeButton = overlay.querySelector('.site-announcement-close');
                if (closeButton) closeButton.focus({ preventScroll: true });
            });
        });
    }

    function scheduleAnnouncement() {
        if (noticeWasDismissed()) return;

        if (document.getElementById('cookie-consent')) {
            waitForCookieNotice();
        } else {
            showTimer = window.setTimeout(openAnnouncement, INITIAL_DELAY_MS);
        }
    }

    function init() {
        if (noticeWasDismissed()) return;

        if (document.readyState === 'complete') {
            scheduleAnnouncement();
        } else {
            window.addEventListener('load', scheduleAnnouncement, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
