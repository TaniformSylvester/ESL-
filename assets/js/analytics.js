// Minimal GA4 funnel instrumentation for the TeachLuma download funnel
// (homepage/listing -> resource -> download click -> outcome) and the
// money funnel (upgrade click -> begin_checkout; purchase and
// payment_submitted are sent by the server, see includes/header.php). Only fires
// gtag('event', ...) calls — never intercepts a click, never blocks or
// delays navigation, and does nothing at all if gtag isn't present (e.g.
// GA disabled, or the script itself blocked by an ad blocker).

// Elements can also declare their own event in markup:
//   data-ga-event="upgrade_click" data-ga-params='{"location":"pricing_page"}'
// Links and buttons send it when clicked, forms when submitted. Params may
// carry value_thb and value_usd instead of value/currency; the one matching
// the price the visitor is seeing (assets/js/currency.js) is sent.
function sendDeclaredEvent(el) {
    var params = {};
    try {
        params = JSON.parse(el.getAttribute('data-ga-params') || '{}') || {};
    } catch (e) {
        params = {};
    }

    if ('value_thb' in params || 'value_usd' in params) {
        var currency = typeof window.TeachLumaCurrency === 'function' ? window.TeachLumaCurrency() : 'THB';
        params.currency = currency;
        params.value = currency === 'USD' ? params.value_usd : params.value_thb;
        if (Array.isArray(params.items)) {
            params.items.forEach(function (item) {
                item.price = params.value;
                item.quantity = item.quantity || 1;
            });
        }
        delete params.value_thb;
        delete params.value_usd;
    }

    params.transport_type = 'beacon';
    gtag('event', el.getAttribute('data-ga-event'), params);
}

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-ga-event]');
        if (form && typeof gtag === 'function') {
            sendDeclaredEvent(form);
        }
    });

    document.body.addEventListener('click', function (event) {
        if (typeof gtag !== 'function') {
            return;
        }

        var declared = event.target.closest('a[data-ga-event], button[data-ga-event]');
        if (declared) {
            sendDeclaredEvent(declared);
        }

        var card = event.target.closest('.resource-card');
        if (card && event.target.closest('a')) {
            gtag('event', 'resource_click', {
                resource_id: card.dataset.resourceId || '',
                resource_title: card.dataset.resourceTitle || '',
                subject: card.dataset.subject || '',
                grade: card.dataset.grade || '',
                transport_type: 'beacon',
            });
            return;
        }

        var downloadLink = event.target.closest('.js-download-link');
        if (downloadLink) {
            gtag('event', 'download_click', {
                resource_id: downloadLink.dataset.resourceId || '',
                resource_title: downloadLink.dataset.resourceTitle || '',
                is_free: downloadLink.dataset.isFree === '1',
                account_state: downloadLink.dataset.accountState || '',
                transport_type: 'beacon',
            });
        }
    });
});
