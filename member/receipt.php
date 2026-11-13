<?php
/**
 * Printable receipt for a paid Teacher Pro membership payment, so a
 * teacher can claim the cost from their school. Teachers save it as PDF
 * with the browser's Print dialog ("Save as PDF").
 *
 * Only the payment's own teacher (or an admin) can open it, and only for
 * an approved payment. Seller details come from Admin > Settings > Receipt
 * details; nothing about tax is printed unless the admin sets a tax ID or
 * tax note there, so the receipt never claims a tax status it can't know.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/settings-functions.php';

require_login();
$viewer = current_user();

$payment = get_receipt_payment((int)($_GET['id'] ?? 0), (int)$viewer['id'], is_admin());
if (!$payment) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$receiptNo = receipt_number($payment);
$paidOn = $payment['payment_date'] ?: $payment['created_at'];
$amount = format_payment_amount($payment);
$currency = strtoupper((string)($payment['currency'] ?? CURRENCY));
$reference = $payment['reference_number'] ?: ($payment['gateway_reference'] ?? '');

$seller = [
    'name'    => get_setting('receipt_business_name', SITE_NAME),
    'address' => get_setting('receipt_business_address'),
    'tax_id'  => get_setting('receipt_tax_id'),
    'note'    => get_setting('receipt_note'),
];
$buyerName = trim($payment['first_name'] . ' ' . $payment['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt <?= e($receiptNo) ?> — <?= e(SITE_NAME) ?></title>
    <link rel="icon" href="<?= e(asset_url('images/favicon.ico')) ?>">
    <style>
        :root { --navy: #172A6E; --yellow: #FBB829; --ink: #1F2433; --muted: #5B6275; --line: #DFE3EC; --soft: #F4F6FB; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #EEF1F8; color: var(--ink); font: 15px/1.5 "Nunito", "Segoe UI", Arial, Helvetica, sans-serif; }
        .toolbar { max-width: 820px; margin: 24px auto 0; padding: 0 16px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; }
        .toolbar a { color: var(--navy); font-weight: 700; }
        .btn { display: inline-block; border: 0; border-radius: 10px; background: var(--navy); color: #fff; font: 700 15px/1 inherit; font-family: inherit; padding: 12px 20px; cursor: pointer; }
        .sheet { max-width: 820px; margin: 16px auto 40px; background: #fff; border-radius: 12px; padding: 48px 52px; box-shadow: 0 2px 12px rgba(23, 42, 110, .08); }
        .head { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; border-bottom: 3px solid var(--navy); padding-bottom: 20px; }
        .brand { font: 700 28px/1.1 "Fredoka", "Arial Rounded MT Bold", Arial, sans-serif; color: var(--navy); }
        .brand span { color: #E39A00; }
        .seller { color: var(--muted); font-size: 13px; margin-top: 8px; white-space: pre-line; }
        .doc { text-align: right; }
        .doc h1 { margin: 0; font: 700 26px/1.1 "Fredoka", Arial, sans-serif; color: var(--navy); letter-spacing: .5px; }
        .doc .paid { display: inline-block; margin-top: 10px; border: 2px solid #1B7A40; color: #1B7A40; border-radius: 6px; padding: 3px 12px; font-weight: 800; letter-spacing: 2px; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 28px 0; }
        .label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 4px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 4px 16px; margin: 0; font-size: 14px; }
        dt { color: var(--muted); }
        dd { margin: 0; font-weight: 700; word-break: break-all; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); border-bottom: 2px solid var(--line); padding: 8px 6px; }
        td { padding: 14px 6px; border-bottom: 1px solid var(--line); vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; width: min(320px, 100%); margin-top: 16px; }
        .totals div { display: flex; justify-content: space-between; padding: 6px 6px; }
        .totals .grand { border-top: 2px solid var(--navy); margin-top: 6px; padding-top: 10px; font-weight: 800; font-size: 18px; color: var(--navy); }
        .notes { margin-top: 32px; padding: 16px 18px; background: var(--soft); border-radius: 8px; font-size: 13px; color: var(--muted); }
        .notes p { margin: 0 0 6px; } .notes p:last-child { margin: 0; }
        .foot { margin-top: 28px; font-size: 12px; color: var(--muted); text-align: center; }
        @media (max-width: 640px) {
            .sheet { padding: 28px 20px; border-radius: 0; }
            .meta { grid-template-columns: 1fr; }
            .doc { text-align: left; }
        }
        @media print {
            @page { size: A4; margin: 14mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; border-radius: 0; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="<?= e(base_url('member/subscription.php')) ?>">&larr; Back to my subscription</a>
        <button type="button" class="btn" onclick="window.print()">Print or Save as PDF</button>
    </div>

    <main class="sheet">
        <div class="head">
            <div>
                <div class="brand">Teach<span>Luma</span></div>
                <div class="seller"><?php
                    $sellerLines = array_filter([
                        $seller['name'] !== SITE_NAME ? $seller['name'] : '',
                        $seller['address'],
                        preg_replace('#^https?://#', '', SITE_URL),
                        CONTACT_EMAIL,
                        $seller['tax_id'] !== '' ? 'Tax ID: ' . $seller['tax_id'] : '',
                    ], static fn($line) => trim((string)$line) !== '');
                    echo e(implode("\n", $sellerLines));
                ?></div>
            </div>
            <div class="doc">
                <h1>RECEIPT</h1>
                <div class="paid">PAID</div>
            </div>
        </div>

        <div class="meta">
            <div>
                <div class="label">Billed to</div>
                <div style="font-weight:800;font-size:16px;"><?= e($buyerName) ?></div>
                <?php if (!empty($payment['school_name'])): ?><div><?= e($payment['school_name']) ?></div><?php endif; ?>
                <?php if (!empty($payment['country'])): ?><div><?= e($payment['country']) ?></div><?php endif; ?>
                <div style="color:var(--muted);"><?= e($payment['email']) ?></div>
            </div>
            <div>
                <dl>
                    <dt>Receipt no.</dt><dd><?= e($receiptNo) ?></dd>
                    <dt>Payment date</dt><dd><?= e(format_date($paidOn, 'd F Y')) ?></dd>
                    <dt>Payment method</dt><dd><?= e(payment_method_label($payment['method'])) ?></dd>
                    <?php if ($reference !== ''): ?><dt>Reference</dt><dd><?= e($reference) ?></dd><?php endif; ?>
                    <dt>Currency</dt><dd><?= e($currency) ?></dd>
                </dl>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?= e(receipt_line_description($payment)) ?></strong>
                        <div style="color:var(--muted);font-size:13px;">Online access to <?= e(SITE_NAME) ?> members-only teaching resources for one teacher account (<?= e($payment['email']) ?>).</div>
                    </td>
                    <td class="num">1</td>
                    <td class="num"><?= e($amount) ?></td>
                    <td class="num"><?= e($amount) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Subtotal</span><span><?= e($amount) ?></span></div>
            <div class="grand"><span>Total paid</span><span><?= e($amount) ?> <?= e($currency) ?></span></div>
        </div>

        <div class="notes">
            <p>This receipt confirms that the payment above was received in full on <?= e(format_date($paidOn, 'd F Y')) ?>.</p>
            <?php if ($seller['note'] !== ''): ?><p><?= nl2br(e($seller['note'])) ?></p><?php endif; ?>
            <p>Questions about this receipt? Contact <?= e(CONTACT_EMAIL) ?> and quote <?= e($receiptNo) ?>.</p>
        </div>

        <div class="foot"><?= e(SITE_NAME) ?> &middot; <?= e(preg_replace('#^https?://#', '', SITE_URL)) ?> &middot; Printed <?= e(date('d F Y')) ?></div>
    </main>
</body>
</html>
