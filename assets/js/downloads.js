// After clicking a resource's Download link, the browser saves the file
// in the background (Content-Disposition: attachment) without navigating
// away, so resource.php's server-rendered download count and review box
// never learn the download happened. This does NOT intercept or replace
// the real download in any way — it only polls a read-only status
// endpoint afterward and updates the DOM if the server confirms a change.
//
// If the download instead FAILS, member/download.php redirects the
// browser to a freshly-rendered resource.php (with its own flash error) —
// that navigation destroys this page's pending timers automatically, so
// the polling below simply never fires in that case. Nothing here ever
// guesses; every value shown comes from the server's own response.

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        var link = event.target.closest('.js-download-link');
        if (!link) {
            return;
        }

        var resourceId = link.dataset.resourceId;
        var csrf = link.dataset.csrf;
        var knownCount = parseInt(link.dataset.initialCount, 10) || 0;
        var base = window.APP_BASE_URL || '';
        var reviewBoxShown = false;

        // A short retry window rather than a single fixed-delay check, so
        // a slow server response doesn't leave the UI stuck stale — each
        // attempt is a plain read of current state, never a guess.
        var delaysMs = [600, 900, 1500, 2000, 3000];
        var attempt = 0;

        function poll() {
            var params = new URLSearchParams();
            params.set('resource_id', resourceId);
            params.set('csrf_token', csrf);

            fetch(base + '/api/resource-download-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString(),
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        scheduleNext();
                        return;
                    }

                    var changed = false;

                    if (data.download_count > knownCount) {
                        knownCount = data.download_count;
                        var countEl = document.getElementById('resource-download-count');
                        if (countEl) {
                            countEl.textContent = data.download_count;
                        }
                        changed = true;

                        // The count only increases once the server has
                        // actually recorded a successful download (see
                        // record_download() in download-functions.php) —
                        // this is the one funnel event with no page load
                        // of its own to attach a script to, so it rides on
                        // this existing, already-verified success signal.
                        if (typeof gtag === 'function') {
                            gtag('event', 'download_success', { resource_id: resourceId });
                        }
                    }

                    if (data.can_review && !reviewBoxShown) {
                        var reviewBox = document.getElementById('review-box-container');
                        if (reviewBox) {
                            reviewBox.innerHTML = data.review_box_html;
                        }
                        reviewBoxShown = true;
                        changed = true;
                    }

                    if (!changed) {
                        scheduleNext();
                    }
                })
                .catch(function () {
                    scheduleNext();
                });
        }

        function scheduleNext() {
            if (attempt >= delaysMs.length) {
                return;
            }
            var delay = delaysMs[attempt];
            attempt++;
            setTimeout(poll, delay);
        }

        scheduleNext();
    });
});
