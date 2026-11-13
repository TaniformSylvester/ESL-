// Handles clicks on any .favorite-btn (resource cards + resource detail page)
// via a delegated listener, since cards are rendered in loops.

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        var btn = event.target.closest('.favorite-btn');
        if (!btn) {
            return;
        }
        event.preventDefault();

        if (btn.dataset.busy === '1') {
            return;
        }
        btn.dataset.busy = '1';

        var params = new URLSearchParams();
        params.set('resource_id', btn.dataset.resourceId);
        params.set('csrf_token', btn.dataset.csrf);

        var base = window.APP_BASE_URL || '';

        fetch(base + '/api/favorite-toggle.php', {
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

                var icon = btn.querySelector('i');

                if (data.favorited) {
                    btn.classList.add('active');
                    btn.setAttribute('aria-pressed', 'true');
                    btn.setAttribute('title', 'Remove from favorites');
                    icon.classList.remove('fa-regular');
                    icon.classList.add('fa-solid', 'text-danger');
                } else {
                    btn.classList.remove('active');
                    btn.setAttribute('aria-pressed', 'false');
                    btn.setAttribute('title', 'Add to favorites');
                    icon.classList.remove('fa-solid', 'text-danger');
                    icon.classList.add('fa-regular');
                }
            })
            .finally(function () {
                btn.dataset.busy = '0';
            });
    });
});
