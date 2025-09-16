<?php
session_start();

// Include necessary files
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/db.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Nicht eingeloggt']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Nur POST-Requests erlaubt']);
    exit;
}

try {
    $db = get_db_connection();
    
    // Delete all notifications for the current user
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $success = $stmt->execute([$user['id']]);
    
    if ($success) {
        $deletedCount = $stmt->rowCount();
        echo json_encode([
            'ok' => true, 
            'message' => 'Alle Benachrichtigungen gelöscht',
            'deleted_count' => $deletedCount
        ]);
    } else {
        throw new Exception('Fehler beim Löschen der Benachrichtigungen');
    }
    
} catch (Exception $e) {
    error_log("Error deleting all notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Serverfehler beim Löschen: ' . $e->getMessage()]);
}
?>