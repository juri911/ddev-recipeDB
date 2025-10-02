<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/notifications.php';

header('Content-Type: application/json');

// Session starten
session_start();

// Prüfen ob User eingeloggt ist
$user = current_user();
if (!$user) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $unreadCount = count_unread_notifications((int)$user['id']);
    echo json_encode(['count' => $unreadCount]);
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    echo json_encode(['count' => 0]);
}
?>
