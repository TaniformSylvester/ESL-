// Click-to-play for the homepage Teaching Demo video (includes/teaching-demo-card.php).
// Nothing loads (no YouTube iframe, no video file) until the visitor
// actually clicks — the poster image is the only thing fetched up front,
// keeping the homepage fast and never autoplaying anything.

document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (event) {
        var btn = event.target.closest('.teaching-demo-play-btn');
        if (!btn) {
            return;
        }

        var player = btn.closest('.teaching-demo-player');
        if (!player) {
            return;
        }

        var type = player.dataset.videoType;
        var title = player.dataset.videoTitle || 'Teaching demo';
        var embed;

        if (type === 'youtube' && player.dataset.videoId) {
            embed = document.createElement('iframe');
            embed.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(player.dataset.videoId) + '?autoplay=1&rel=0';
            embed.title = title;
            embed.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
            embed.setAttribute('allowfullscreen', '');
            embed.className = 'w-100 h-100 border-0';
        } else if (type === 'local' && player.dataset.videoUrl) {
            embed = document.createElement('video');
            embed.src = player.dataset.videoUrl;
            embed.controls = true;
            embed.autoplay = true;
            embed.className = 'w-100 h-100';
            embed.setAttribute('aria-label', title);
        } else {
            return;
        }

        player.innerHTML = '';
        player.appendChild(embed);
    });
});
