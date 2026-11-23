<?php
/**
 * One-click unsubscribe from promotional email (the welcome drip and any
 * future tips/offers). Linked from every drip email as
 * /unsubscribe.php?token=<users.email_unsubscribe_token>.
 *
 *   * GET with a valid token: opts the account out immediately, no login
 *     needed, then offers a "Resubscribe" button.
 *   * POST "List-Unsubscribe=One-Click" (RFC 8058, sent by Gmail/Yahoo/Apple
 *     Mail's own unsubscribe button): opts out and returns a plain 200.
 *   * POST action=resubscribe (the button above, CSRF-protected): opts back in.
 *
 * Only users.marketing_opt_out changes. Transactional email (password
 * resets, payment and membership notices) never checks it.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/drip-functions.php';

// The token is a secret: never leak this URL to other sites via Referer.
header('Referrer-Policy: no-referrer');

$token = strtolower(trim((string)($_GET['token'] ?? $_POST['token'] ?? '')));
$account = $token !== '' ? find_user_by_unsubscribe_token($token) : null;

// RFC 8058 one-click: the mail provider POSTs with no session or CSRF token;
// the unguessable token in the URL is the authorization.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
    if ($account) {
        set_marketing_opt_out((int)$account['id'], true);
    }
    http_response_code($account ? 200 : 404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $account ? 'Unsubscribed.' : 'Unknown link.';
    exit;
}

$state = 'invalid';

if ($account) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resubscribe') {
        require_csrf();
        set_marketing_opt_out((int)$account['id'], false);
        $state = 'resubscribed';
    } else {
        if (empty($account['marketing_opt_out'])) {
            set_marketing_opt_out((int)$account['id'], true);
        }
        $state = 'unsubscribed';
    }
}

$pageTitle = $state === 'resubscribed' ? 'Subscribed again' : 'Unsubscribe';
$pageRobots = 'noindex, nofollow';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5 text-center">
                    <?php if ($state === 'unsubscribed'): ?>
                        <div class="display-6 mb-3" aria-hidden="true">✉️</div>
                        <h1 class="h3 fw-bold mb-3">You're unsubscribed</h1>
                        <p class="text-secondary mb-2">
                            We won't send tips, offers or welcome-series emails to <strong><?= e(email_mask_address($account['email'])) ?></strong> any more.
                        </p>
                        <p class="text-secondary small mb-4">
                            You'll still get account emails you need, such as password resets, payment receipts and membership notices.
                        </p>
                        <form method="post" action="<?= e(base_url('unsubscribe.php')) ?>">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <input type="hidden" name="action" value="resubscribe">
                            <p class="small text-secondary mb-2">Unsubscribed by mistake?</p>
                            <button type="submit" class="btn btn-outline-primary">Keep sending me teaching tips</button>
                        </form>
                    <?php elseif ($state === 'resubscribed'): ?>
                        <div class="display-6 mb-3" aria-hidden="true">🎉</div>
                        <h1 class="h3 fw-bold mb-3">Welcome back!</h1>
                        <p class="text-secondary mb-4">You'll receive teaching tips and offers from <?= e(SITE_NAME) ?> again. You can unsubscribe any time from the link in any of those emails.</p>
                        <a href="<?= e(base_url('resources.php')) ?>" class="btn btn-primary">Browse resources</a>
                    <?php else: ?>
                        <h1 class="h3 fw-bold mb-3">This unsubscribe link isn't valid</h1>
                        <p class="text-secondary mb-4">
                            It may have been copied incompletely. Please use the link in your most recent email from us,
                            or write to <a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a> and we'll unsubscribe you.
                        </p>
                        <a href="<?= e(base_url()) ?>" class="btn btn-outline-secondary">Go to the homepage</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
