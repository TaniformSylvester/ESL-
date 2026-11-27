// Shows prices in baht (THB) or US dollars (USD) — see includes/currency-functions.php.
//
// The server renders its best guess. This script makes the page match the
// visitor's saved choice (the tl_currency cookie) and, on a first visit
// with no saved choice, guesses from the browser's time zone: a browser
// set to Thailand's time zone gets baht, everyone else gets dollars. The
// guess is saved so the next page the server renders already matches.
(function () {
    'use strict';

    var COOKIE = 'tl_currency';
    var root = document.documentElement;

    function readCookie() {
        var match = document.cookie.match(/(?:^|;\s*)tl_currency=(THB|USD)(?:;|$)/);
        return match ? match[1] : null;
    }

    function saveCookie(currency) {
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = COOKIE + '=' + currency + '; Max-Age=31536000; Path=/; SameSite=Lax' + secure;
    }

    function guessFromTimeZone() {
        try {
            var zone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            return zone === 'Asia/Bangkok' ? 'THB' : 'USD';
        } catch (e) {
            return root.getAttribute('data-currency') || 'THB';
        }
    }

    function apply(currency) {
        root.setAttribute('data-currency', currency);
        var key = currency === 'USD' ? 'usd' : 'thb';

        document.querySelectorAll('.js-price').forEach(function (el) {
            var text = el.getAttribute('data-' + key);
            if (text !== null) {
                el.textContent = text;
            }
        });

        document.querySelectorAll('[data-currency-only]').forEach(function (el) {
            el.hidden = el.getAttribute('data-currency-only') !== currency;
        });

        document.querySelectorAll('input[name="currency"][data-follow-currency]').forEach(function (input) {
            input.value = currency;
        });

        document.querySelectorAll('[data-currency-choice]').forEach(function (link) {
            var active = link.getAttribute('data-currency-choice') === currency;
            link.classList.toggle('active', active);
            link.classList.toggle('fw-bold', active);
            if (active) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    var currency = readCookie();
    if (!currency) {
        currency = guessFromTimeZone();
        saveCookie(currency);
    }
    if (currency !== root.getAttribute('data-currency')) {
        apply(currency);
    }

    // The "Prices in" switch: save the choice and update the page in place.
    // (Without JavaScript the links still work through the server.)
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-currency-choice]');
        if (!link) {
            return;
        }
        event.preventDefault();
        var chosen = link.getAttribute('data-currency-choice');
        saveCookie(chosen);
        apply(chosen);
    });

    window.TeachLumaCurrency = function () {
        return root.getAttribute('data-currency') || 'THB';
    };
})();
