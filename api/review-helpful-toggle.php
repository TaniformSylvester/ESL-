<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to mark reviews as helpful.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$reviewId = (int)($_POST['review_id'] ?? 0);

if ($reviewId <= 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Review not found.']);
    exit;
}

$result = toggle_review_helpful((int)$_SESSION['user_id'], $reviewId);

if ($result === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'This review cannot be marked helpful.']);
    exit;
}

echo json_encode(['success' => true, 'helpful' => $result['helpful'], 'count' => $result['count']]);
