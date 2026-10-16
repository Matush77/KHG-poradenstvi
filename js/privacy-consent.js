(function () {
    'use strict';

    // Web používá jen technické lokální úložiště, a proto nezobrazuje cookie
    // oznámení. Klíče starších verzí oznámení se u návštěvníků smažou.
    const LEGACY_STORAGE_KEYS = [
        'khg_cookie_notice_v1',
        'khg_privacy_consent_v1',
        'khg_price_notice_20260901_v1'
    ];
    const QUIZ_PROGRESS_KEYS = [
        'quiz-overthinking-progress',
        'quiz-prokrastinace-progress',
        'quiz-toxicky-vztah-progress',
        'quiz-zdrave-hranice-progress'
    ];
    const QUIZ_PROGRESS_LIFETIME_MS = 24 * 60 * 60 * 1000;

    function removeLegacyStorage() {
        LEGACY_STORAGE_KEYS.forEach(function (key) {
            try {
                localStorage.removeItem(key);
            } catch (error) {
                // Bez lokálního úložiště není co mazat.
            }
        });
    }

    // localStorage nemá expiraci, proto rozepsané kvízy starší než 24 hodin
    // mažeme při každém načtení libovolné stránky webu.
    function removeExpiredQuizProgress() {
        QUIZ_PROGRESS_KEYS.forEach(function (key) {
            try {
                const raw = localStorage.getItem(key);
                if (raw === null) return;

                let saved = null;
                try {
                    saved = JSON.parse(raw);
                } catch (error) {
                    // Poškozený záznam se smaže níže.
                }

                const isFresh = saved &&
                    Number.isFinite(saved.timestamp) &&
                    Date.now() - saved.timestamp < QUIZ_PROGRESS_LIFETIME_MS;

                if (!isFresh) localStorage.removeItem(key);
            } catch (error) {
                // Bez lokálního úložiště není co mazat.
            }
        });
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

    removeLegacyStorage();
    removeExpiredQuizProgress();
    clearLegacyGoogleCookies();
})();
