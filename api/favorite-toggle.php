<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/resource-functions.php';
require_once __DIR__ . '/../includes/favorites-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to favorite resources.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$resourceId = (int)($_POST['resource_id'] ?? 0);
$resource = $resourceId > 0 ? get_resource_by_id($resourceId) : null;

if (!$resource) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Resource not found.']);
    exit;
}

$favorited = toggle_favorite((int)$_SESSION['user_id'], $resourceId);

echo json_encode(['success' => true, 'favorited' => $favorited]);
