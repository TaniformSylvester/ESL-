// Handles clicks on any .review-helpful-btn (resource detail page) via a
// delegated listener, mirroring favorites.js's pattern.

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        var btn = event.target.closest('.review-helpful-btn');
        if (!btn) {
            return;
        }
        event.preventDefault();

        if (btn.dataset.busy === '1') {
            return;
        }
        btn.dataset.busy = '1';

        var params = new URLSearchParams();
        params.set('review_id', btn.dataset.reviewId);
        params.set('csrf_token', btn.dataset.csrf);

        var base = window.APP_BASE_URL || '';

        fetch(base + '/api/review-helpful-toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    return;
                }

                var countEl = btn.querySelector('.helpful-count');
                countEl.textContent = data.count;

                if (data.helpful) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            })
            .finally(function () {
                btn.dataset.busy = '0';
            });
    });
});
