/**
 * "Scan to pay" panel on member/subscription.php.
 *
 * - Draws the PromptPay QR for the selected plan (the payload, with the
 *   price built in, comes from PHP in data-payload-* attributes).
 * - Switches plan (Monthly/Annual): updates the QR, the amount shown and
 *   the plan submitted with the slip.
 * - "Save QR image": on a phone you can't scan your own screen, so this
 *   saves a PNG to open with the banking app's "scan from photo" option.
 * - The "Scan to pay" buttons on the plan cards open the panel on that
 *   plan without reloading. Without JS they're plain links that do the same.
 */
(function () {
    'use strict';

    var panel = document.getElementById('scan-to-pay');
    if (!panel) {
        return;
    }

    var qrBox = panel.querySelector('[data-qr]');
    var amountEls = panel.querySelectorAll('[data-scan-amount]');
    var planInput = panel.querySelector('input[name="plan"]');
    var planButtons = panel.querySelectorAll('[data-scan-plan]');
    var saveLink = panel.querySelector('[data-qr-save]');
    var hasGenerator = qrBox && typeof window.qrcode === 'function';

    function buildQr(payload) {
        var qr = window.qrcode(0, 'M');
        qr.addData(payload);
        qr.make();
        return qr;
    }

    // Crisp, scalable SVG for the screen.
    function qrSvg(qr, label) {
        var count = qr.getModuleCount();
        var margin = 4;
        var size = count + margin * 2;
        var path = '';
        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (qr.isDark(r, c)) {
                    path += 'M' + (c + margin) + ' ' + (r + margin) + 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" role="img" aria-label="' + label + '" shape-rendering="crispEdges">'
            + '<rect width="' + size + '" height="' + size + '" fill="#fff"/>'
            + '<path d="' + path + '" fill="#000"/></svg>';
    }

    // PNG for saving, with the amount printed under the code.
    function qrPng(qr, caption) {
        var count = qr.getModuleCount();
        var cell = 10;
        var margin = 4 * cell;
        var qrSize = count * cell + margin * 2;
        var captionHeight = 56;
        var canvas = document.createElement('canvas');
        canvas.width = qrSize;
        canvas.height = qrSize + captionHeight;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#000';
        for (var r = 0; r < count; r++) {
            for (var c = 0; c < count; c++) {
                if (qr.isDark(r, c)) {
                    ctx.fillRect(margin + c * cell, margin + r * cell, cell, cell);
                }
            }
        }
        ctx.fillStyle = '#172A6E';
        ctx.font = 'bold 26px Nunito, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(caption, qrSize / 2, qrSize + 22);
        return canvas.toDataURL('image/png');
    }

    function selectPlan(plan) {
        if (plan !== 'annual') {
            plan = 'monthly';
        }
        if (planInput) {
            planInput.value = plan;
        }

        var amountText = panel.getAttribute('data-amount-' + plan) || '';
        amountEls.forEach(function (el) { el.textContent = amountText; });

        planButtons.forEach(function (btn) {
            btn.setAttribute('aria-pressed', btn.getAttribute('data-scan-plan') === plan ? 'true' : 'false');
        });

        if (hasGenerator) {
            var payload = qrBox.getAttribute('data-payload-' + plan);
            var qr = buildQr(payload);
            qrBox.innerHTML = qrSvg(qr, 'PromptPay QR code for ' + amountText);
            if (saveLink) {
                saveLink.href = qrPng(qr, 'PromptPay ' + amountText);
                saveLink.setAttribute('download', (panel.getAttribute('data-file-prefix') || 'promptpay') + '-' + plan + '.png');
                saveLink.hidden = false;
            }
        }
    }

    planButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            selectPlan(btn.getAttribute('data-scan-plan'));
        });
    });

    // Plan cards' "Scan to pay" buttons: open the panel on that plan in place.
    document.querySelectorAll('[data-open-scan]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            selectPlan(link.getAttribute('data-open-scan'));
            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            var heading = panel.querySelector('h2');
            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
            }
        });
    });

    // Phone camera photos are often bigger than the 3 MB upload limit. Shrink
    // big ones to a JPEG that's still easily readable before they're sent.
    var slipInput = panel.querySelector('input[type="file"][name="screenshot"]');
    var MAX_BYTES = 2.5 * 1024 * 1024;
    var MAX_SIDE = 2000;

    if (slipInput && window.DataTransfer && window.URL && window.HTMLCanvasElement) {
        slipInput.addEventListener('change', function () {
            var file = slipInput.files && slipInput.files[0];
            if (!file || file.size <= MAX_BYTES || !/^image\//.test(file.type)) {
                return;
            }
            var img = new Image();
            var url = URL.createObjectURL(file);
            img.onload = function () {
                var scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(img.naturalWidth * scale);
                canvas.height = Math.round(img.naturalHeight * scale);
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                URL.revokeObjectURL(url);
                canvas.toBlob(function (blob) {
                    if (!blob || blob.size >= file.size) {
                        return;
                    }
                    var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    var transfer = new DataTransfer();
                    transfer.items.add(new File([blob], name, { type: 'image/jpeg' }));
                    slipInput.files = transfer.files;
                }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(url); };
            img.src = url;
        });
    }

    selectPlan(planInput ? planInput.value : 'monthly');
})();
