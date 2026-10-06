<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';
require_once __DIR__ . '/../includes/membership.php';
require_once __DIR__ . '/../includes/payment-functions.php';
require_once __DIR__ . '/../includes/email.php';

require_admin();
$admin = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? '');
    $paymentId = (int)($_POST['id'] ?? 0);

    if ($action === 'approve') {
        $payment = approve_payment($paymentId, $admin['id']);

        if ($payment) {
            $membership = get_membership((int)$payment['user_id']);
            send_payment_approved_email(
                ['first_name' => $payment['first_name'], 'email' => $payment['email']],
                $membership['expiry_date'] ?? ''
            );
            log_admin_action($admin['id'], 'approve_payment', "Approved payment #{$paymentId} for {$payment['email']}");
            flash_set('success', 'Payment approved and membership activated.');
        } else {
            flash_set('error', 'That payment could not be approved (it may already have been processed).');
        }

        redirect('admin/payments.php');
    } elseif ($action === 'reject') {
        $note = clean_input($_POST['admin_note'] ?? '');
        $payment = reject_payment($paymentId, $admin['id'], $note);

        if ($payment) {
            send_payment_rejected_email(
                ['first_name' => $payment['first_name'], 'email' => $payment['email']],
                $note
            );
            log_admin_action($admin['id'], 'reject_payment', "Rejected payment #{$paymentId} for {$payment['email']}");
            flash_set('success', 'Payment rejected.');
        } else {
            flash_set('error', 'That payment could not be rejected (it may already have been processed).');
        }

        redirect('admin/payments.php');
    }
}

$filters = [
    'status' => trim((string)($_GET['status'] ?? 'pending')),
    'search' => trim((string)($_GET['search'] ?? '')),
];
$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_all_payments_paginated($filters, $page, ADMIN_ROWS_PER_PAGE);

$pageTitle = 'Payments';
require_once __DIR__ . '/../includes/admin-header.php';
?>
<ul class="nav nav-pills mb-4">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $value => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filters['status'] === $value ? 'active' : '' ?>"
               href="<?= e(base_url('admin/payments.php?status=' . $value)) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<form method="get" action="<?= e(base_url('admin/payments.php')) ?>" class="row g-2 mb-4">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email, reference&hellip;" value="<?= e($filters['search']) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-outline-secondary">Search</button>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Payment Date</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['items'])): ?>
                    <tr><td colspan="9" class="text-center text-secondary py-4">No payments found.</td></tr>
                <?php endif; ?>
                <?php foreach ($result['items'] as $payment): ?>
                    <tr>
                        <td>
                            <?= e($payment['first_name'] . ' ' . $payment['last_name']) ?>
                            <div class="small text-secondary"><?= e($payment['email']) ?></div>
                        </td>
                        <td class="small"><?= e(ucfirst($payment['plan'] ?? 'monthly')) ?></td>
                        <td><?= format_payment_amount($payment) ?></td>
                        <td class="small"><?= e(payment_method_label($payment['method'])) ?></td>
                        <td class="small"><?= e($payment['reference_number']) ?></td>
                        <td class="small text-secondary"><?= e(format_date($payment['payment_date'])) ?></td>
                        <td>
                            <?php if (!empty($payment['screenshot_path'])): ?>
                                <a href="<?= e(base_url('admin/payment-screenshot.php?id=' . $payment['id'])) ?>" target="_blank" class="small">View</a>
                            <?php else: ?>
                                <span class="text-secondary small">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e(payment_status_badge_class($payment['status'])) ?>"><?= e(ucfirst($payment['status'])) ?></span>
                            <?php if ($payment['status'] === 'rejected' && !empty($payment['admin_note'])): ?>
                                <div class="small text-secondary mt-1"><?= e($payment['admin_note']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($payment['status'] === 'pending'): ?>
                                <form method="post" action="<?= e(base_url('admin/payments.php')) ?>" class="d-inline"
                                      onsubmit="return confirm('Approve this payment and activate the member\'s subscription?');">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?= (int)$payment['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#reject-<?= (int)$payment['id'] ?>">
                                    Reject
                                </button>
                                <div class="collapse mt-2" id="reject-<?= (int)$payment['id'] ?>">
                                    <form method="post" action="<?= e(base_url('admin/payments.php')) ?>"
                                          onsubmit="return confirm('Reject this payment?');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="id" value="<?= (int)$payment['id'] ?>">
                                        <input type="text" name="admin_note" class="form-control form-control-sm mb-2" placeholder="Reason (shown to the teacher)" style="min-width:220px;">
                                        <button type="submit" class="btn btn-sm btn-danger">Confirm Reject</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary small">
                                    <?= $payment['status'] === 'approved' ? 'Approved' : 'Rejected' ?> <?= e(format_date($payment['reviewed_at'] ?? '')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4"><?= render_pagination($result['page'], $result['total_pages']) ?></div>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
