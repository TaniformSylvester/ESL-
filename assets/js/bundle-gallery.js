// Bundle detail page (bundle.php): thumbnail gallery + preview lightbox,
// plus the "Copy Link" share button (mirroring resource.php's inline
// version). Defensive no-ops when the page has none of these elements.
(function () {
    var gallery = document.getElementById('bundleGallery');

    if (gallery) {
        var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('.bundle-gallery-thumb'));
        var mainImage = document.getElementById('bundleGalleryMainImage');
        var viewPreviewBtn = document.getElementById('bundleViewPreviewBtn');
        var lightbox = document.getElementById('bundleLightbox');
        var lightboxImage = document.getElementById('bundleLightboxImage');
        var lightboxClose = document.getElementById('bundleLightboxClose');
        var lightboxPrev = document.getElementById('bundleLightboxPrev');
        var lightboxNext = document.getElementById('bundleLightboxNext');

        var currentIndex = 0;
        var lastFocusedEl = null;

        function setActiveThumb(index) {
            thumbs.forEach(function (thumb, i) {
                var active = i === index;
                thumb.classList.toggle('active', active);
                thumb.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function showImage(index) {
            if (!thumbs.length) { return; }
            if (index < 0) { index = thumbs.length - 1; }
            if (index >= thumbs.length) { index = 0; }
            currentIndex = index;

            var url = thumbs[index].getAttribute('data-full');
            if (mainImage) { mainImage.src = url; }
            setActiveThumb(index);
            if (lightboxImage && lightbox && !lightbox.hidden) {
                lightboxImage.src = url;
            }
        }

        thumbs.forEach(function (thumb, i) {
            thumb.addEventListener('click', function () { showImage(i); });
        });

        gallery.addEventListener('keydown', function (event) {
            var target = event.target;
            if (!target.classList || !target.classList.contains('bundle-gallery-thumb')) { return; }
            if (event.key !== 'ArrowRight' && event.key !== 'ArrowDown' && event.key !== 'ArrowLeft' && event.key !== 'ArrowUp') { return; }

            event.preventDefault();
            var delta = (event.key === 'ArrowRight' || event.key === 'ArrowDown') ? 1 : -1;
            var next = (currentIndex + delta + thumbs.length) % thumbs.length;
            showImage(next);
            thumbs[next].focus();
        });

        function openLightbox() {
            if (!lightbox || !thumbs.length) { return; }
            lastFocusedEl = document.activeElement;
            lightboxImage.src = thumbs[currentIndex].getAttribute('data-full');
            lightbox.hidden = false;
            document.body.classList.add('bundle-lightbox-open');
            if (lightboxClose) { lightboxClose.focus(); }
        }

        function closeLightbox() {
            if (!lightbox) { return; }
            lightbox.hidden = true;
            document.body.classList.remove('bundle-lightbox-open');
            if (lastFocusedEl && typeof lastFocusedEl.focus === 'function') {
                lastFocusedEl.focus();
            }
        }

        if (viewPreviewBtn) { viewPreviewBtn.addEventListener('click', openLightbox); }
        if (lightboxClose) { lightboxClose.addEventListener('click', closeLightbox); }
        if (lightboxPrev) { lightboxPrev.addEventListener('click', function () { showImage(currentIndex - 1); }); }
        if (lightboxNext) { lightboxNext.addEventListener('click', function () { showImage(currentIndex + 1); }); }

        if (lightbox) {
            // Click on the dark backdrop (not the image/controls) closes it.
            lightbox.addEventListener('click', function (event) {
                if (event.target === lightbox) { closeLightbox(); }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (!lightbox || lightbox.hidden) { return; }
            if (event.key === 'Escape') { closeLightbox(); }
            else if (event.key === 'ArrowLeft') { showImage(currentIndex - 1); }
            else if (event.key === 'ArrowRight') { showImage(currentIndex + 1); }
        });

        // Touch swipe on the lightbox image (left/right to navigate).
        var touchStartX = null;
        if (lightboxImage) {
            lightboxImage.addEventListener('touchstart', function (event) {
                touchStartX = event.changedTouches[0].clientX;
            }, { passive: true });

            lightboxImage.addEventListener('touchend', function (event) {
                if (touchStartX === null) { return; }
                var delta = event.changedTouches[0].clientX - touchStartX;
                if (Math.abs(delta) > 40) {
                    showImage(currentIndex + (delta < 0 ? 1 : -1));
                }
                touchStartX = null;
            }, { passive: true });
        }
    }

    var copyBtn = document.getElementById('copyBundleLink');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            navigator.clipboard.writeText(copyBtn.dataset.url).then(function () {
                var icon = copyBtn.querySelector('i');
                icon.classList.remove('fa-link');
                icon.classList.add('fa-check');
                setTimeout(function () {
                    icon.classList.remove('fa-check');
                    icon.classList.add('fa-link');
                }, 1500);
            });
        });
    }
})();
