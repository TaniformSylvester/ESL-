<?php
/**
 * Records a real TeachLuma Games play event. Called via fetch() directly
 * from inside a game's own self-contained HTML (assets/games/<slug>/index.html)
 * — games require no login and are static files with no session-rendered
 * CSRF field available, so this endpoint is deliberately public and
 * unauthenticated, same as the public contact form. The only "state"
 * it can change is an anonymous play counter, so the usual CSRF threat
 * (a malicious site using a logged-in visitor's session against them)
 * doesn't apply here in any way that matters.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/games-functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
$slug = trim((string)($input['slug'] ?? ''));
$eventType = trim((string)($input['event'] ?? ''));

if (!in_array($eventType, ['started', 'completed'], true) || !get_game_by_slug($slug)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown game or event.']);
    exit;
}

// A short debounce only — guards against an accidental double-fire from a
// single click, never a real cap on legitimate repeated play (a teacher
// replaying the same game many times in one class period should all count).
$rateLimitKey = 'game_play:' . $slug . ':' . $eventType;
if (!too_many_attempts($rateLimitKey, 5, 3)) {
    record_attempt($rateLimitKey);
    record_game_play($slug, $eventType);
}

echo json_encode(['success' => true]);
