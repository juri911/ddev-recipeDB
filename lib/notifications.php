<?php
require_once __DIR__ . '/../lib/db.php';

function add_notification(int $userId, string $type, ?int $entityId, string $message): void {
    db_query('INSERT INTO notifications (user_id, type, entity_id, message) VALUES (?,?,?,?)', [
        $userId, $type, $entityId, $message
    ]);
}

function get_notifications(int $userId, int $limit = 20, int $offset = 0): array {
    return db_query('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?', [$userId, $limit, $offset])->fetchAll();
}

function mark_notification_as_read(int $notificationId, int $userId): void {
    db_query('UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?', [$notificationId, $userId]);
}

function count_unread_notifications(int $userId): int {
    return (int)db_query('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE', [$userId])->fetchColumn();
}
