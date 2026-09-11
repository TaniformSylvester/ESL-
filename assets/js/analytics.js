// Minimal GA4 funnel instrumentation for the TeachLuma download funnel
// (homepage/listing -> resource -> download click -> outcome). Only fires
// gtag('event', ...) calls — never intercepts a click, never blocks or
// delays navigation, and does nothing at all if gtag isn't present (e.g.
// GA disabled, or the script itself blocked by an ad blocker).

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        if (typeof gtag !== 'function') {
            return;
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
