<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/notifications.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json');

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$notificationIds = $input['notification_ids'] ?? [];
$csrfToken = $input['csrf_token'] ?? '';

if (!csrf_validate_token($csrfToken)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if (empty($notificationIds) || !is_array($notificationIds)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid notification IDs']);
    exit;
}

try {
    foreach ($notificationIds as $id) {
        mark_notification_as_read((int)$id, (int)$user['id']);
    }
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log("Error marking notifications as read: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to mark notifications as read']);
}
