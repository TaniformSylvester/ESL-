<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/payment-functions.php';

require_admin();

$id = (int)($_GET['id'] ?? 0);
$payment = $id > 0 ? get_payment_by_id($id) : null;

if (!$payment || empty($payment['screenshot_path'])) {
    http_response_code(404);
    exit;
}

$filePath = UPLOAD_BASE_PATH . '/payment-proofs/' . $payment['screenshot_path'];

if (!is_file($filePath)) {
    http_response_code(404);
    exit;
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png'  => 'image/png',
    'webp' => 'image/webp',
    default => 'image/jpeg',
};

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');

readfile($filePath);
exit;
