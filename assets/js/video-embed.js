// Click-to-play for embedded educational videos (video.php, and resource.php's
// "Watch the Lesson" section). Same technique as assets/js/teaching-demo.js:
// nothing loads (no YouTube iframe) until the visitor actually clicks — the
// poster thumbnail is the only thing fetched up front, and the embed never
// autoplays on page load.

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        var btn = event.target.closest('.video-embed-play-btn');
        if (!btn) {
            return;
        }

        var player = btn.closest('.video-embed-player');
        if (!player || !player.dataset.videoId) {
            return;
        }

        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(player.dataset.videoId) + '?autoplay=1&rel=0';
        iframe.title = player.dataset.videoTitle || 'Video';
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        iframe.setAttribute('allowfullscreen', '');
        iframe.className = 'w-100 h-100 border-0';
        iframe.loading = 'lazy';

        player.innerHTML = '';
        player.appendChild(iframe);
    });
});
