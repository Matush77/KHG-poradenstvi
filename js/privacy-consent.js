(function () {
    'use strict';

    const STORAGE_KEY = 'khg_cookie_notice_v1';
    const LEGACY_CONSENT_KEY = 'khg_privacy_consent_v1';
    const NOTICE_LIFETIME_MS = 365 * 24 * 60 * 60 * 1000;

    function readNoticeState() {
        try {
            localStorage.removeItem(LEGACY_CONSENT_KEY);

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

    function saveNoticeState() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ dismissedAt: Date.now() }));
        } catch (error) {
            // Zavření funguje i bez trvalého uložení volby.
        }
    }

    function clearLegacyGoogleCookies() {
        const names = document.cookie
            .split(';')
            .map(function (cookie) { return cookie.split('=')[0].trim(); })
            .filter(function (name) {
                return /^(_ga|_gid|_gat|_gcl|_gac)/.test(name);
            });
        const hostname = window.location.hostname;
        const domain = hostname && hostname.includes('.') ? '.' + hostname.replace(/^www\./, '') : '';

        names.forEach(function (name) {
            document.cookie = name + '=; Max-Age=0; path=/; SameSite=Lax';
            if (domain) {
                document.cookie = name + '=; Max-Age=0; path=/; domain=' + domain + '; SameSite=Lax';
            }
        });
    }

    function closeNotice(remember) {
        const notice = document.getElementById('cookie-consent');
        if (notice) notice.remove();
        if (remember) saveNoticeState();
    }

    function showNotice(isReopened) {
        closeNotice(false);

        const notice = document.createElement('section');
        notice.id = 'cookie-consent';
        notice.className = 'cookie-consent';
        notice.setAttribute('role', 'region');
        notice.setAttribute('aria-labelledby', 'cookie-consent-title');
        notice.setAttribute('aria-describedby', 'cookie-consent-description');
        notice.innerHTML =
            '<div class="cookie-consent-inner">' +
                '<div class="cookie-consent-copy">' +
                    '<h2 id="cookie-consent-title">Informace o cookies</h2>' +
                    '<p id="cookie-consent-description">Tento web nepoužívá analytické ani marketingové cookies. Nezbytné lokální úložiště slouží pouze k zapamatování tohoto oznámení a rozepsaného kvízu. <a href="/ochrana-osobnich-udaju.html">Podrobnosti o zpracování</a>.</p>' +
                '</div>' +
                '<div class="cookie-consent-actions">' +
                    '<button type="button" class="cookie-choice cookie-notice-dismiss" data-cookie-dismiss>' +
                        (isReopened ? 'Zavřít' : 'Rozumím') +
                    '</button>' +
                '</div>' +
            '</div>';

        notice.addEventListener('click', function (event) {
            if (event.target.closest('[data-cookie-dismiss]')) {
                closeNotice(true);
            }
        });

        document.body.appendChild(notice);
    }

    function ensureFooterControls() {
        document.querySelectorAll('.footer-bottom').forEach(function (footerBottom) {
            if (footerBottom.querySelector('.footer-legal')) return;

            const legal = document.createElement('div');
            legal.className = 'footer-legal';
            legal.innerHTML =
                '<a href="/ochrana-osobnich-udaju.html">Ochrana osobních údajů</a>' +
                '<button type="button" class="footer-legal-button" data-cookie-settings>Informace o cookies</button>';
            footerBottom.appendChild(legal);
        });
    }

    const noticeWasDismissed = readNoticeState();
    clearLegacyGoogleCookies();

    document.addEventListener('DOMContentLoaded', function () {
        ensureFooterControls();

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-cookie-settings]')) {
                showNotice(true);
            }
        });

        if (!noticeWasDismissed) {
            showNotice(false);
        }
    });
})();
