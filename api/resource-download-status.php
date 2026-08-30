<?php
/**
 * Read-only status check used by assets/js/downloads.js to refresh a
 * resource page's download count and review box after a successful
 * download, without a full page reload. This endpoint never writes to
 * the database — it cannot increment a download count or fabricate
 * review eligibility, it only reports current, already-recorded state
 * (the exact same functions resource.php itself uses on a normal load).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/download-functions.php';
require_once __DIR__ . '/../includes/review-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$resourceId = (int)($_POST['resource_id'] ?? 0);
$resource = $resourceId > 0 ? get_resource_by_id($resourceId) : null;

// Same visibility rule as the public resource page — never confirm the
// existence of, or leak any data about, an archived/unpublished resource.
if (!$resource || !$resource['is_published'] || $resource['status'] !== 'active') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Resource not found.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$isLoggedIn = true;
$myReview = get_user_review($userId, $resourceId);
$canWriteReview = can_review_resource($userId, $resourceId);
$reviewErrors = [];

ob_start();
require __DIR__ . '/../includes/review-box.php';
$reviewBoxHtml = ob_get_clean();

echo json_encode([
    'success'         => true,
    'download_count'  => (int)$resource['download_count'],
    'can_review'      => $canWriteReview,
    'review_box_html' => $reviewBoxHtml,
]);
