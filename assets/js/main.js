// ESL Teacher Hub — small shared behaviors. Kept deliberately minimal.

document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash alerts after a few seconds.
    document.querySelectorAll('.alert-dismissible').forEach(function (alertEl) {
        setTimeout(function () {
            var closeBtn = alertEl.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            }
        }, 6000);
    });

    // Add a show/hide toggle button to every password field on the site.
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        var parent = input.parentNode;
        parent.classList.add('position-relative');
        input.classList.add('pe-5');

        var toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-link text-secondary position-absolute top-0 end-0 p-0 me-3 mt-2';
        toggleBtn.setAttribute('aria-label', 'Show password');
        toggleBtn.innerHTML = '<i class="fa-solid fa-eye"></i>';

        toggleBtn.addEventListener('click', function () {
            var isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggleBtn.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
            toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
        });

        input.insertAdjacentElement('afterend', toggleBtn);
    });

    // Gentle reveal-on-scroll for [data-reveal] blocks. The hidden start
    // state only applies under html.js + no reduced-motion (see style.css),
    // so content is always visible if this never runs.
    var revealEls = document.querySelectorAll('[data-reveal]');
    if (!('IntersectionObserver' in window)) {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
        return;
    }
    var revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    revealEls.forEach(function (el) { revealObserver.observe(el); });
});
