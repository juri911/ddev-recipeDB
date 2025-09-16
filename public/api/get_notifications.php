<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/notifications.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json');

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// CSRF token validation for GET requests is typically done via a header or query param
// For simplicity, we're expecting it in the header for GET requests here for consistency
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!csrf_validate_token($csrfToken)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    $notifications = get_notifications((int)$user['id']);
    echo json_encode($notifications);
} catch (Throwable $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to fetch notifications']);
}
