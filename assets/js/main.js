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
});
