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

// Admin-spezifische Funktionen
function get_all_notifications(int $limit = 50, int $offset = 0, array $filters = []): array {
    $whereConditions = [];
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereConditions[] = 'n.message LIKE ?';
        $params[] = "%{$filters['search']}%";
    }
    
    if (!empty($filters['type'])) {
        $whereConditions[] = 'n.type = ?';
        $params[] = $filters['type'];
    }
    
    if (isset($filters['is_read'])) {
        $whereConditions[] = 'n.is_read = ?';
        $params[] = $filters['is_read'] ? 1 : 0;
    }
    
    if (!empty($filters['user_id'])) {
        $whereConditions[] = 'n.user_id = ?';
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = 'DATE(n.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = 'DATE(n.created_at) <= ?';
        $params[] = $filters['date_to'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $params[] = $limit;
    $params[] = $offset;
    
    return db_query("
        SELECT n.*, u.name as user_name, u.email as user_email
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ", $params)->fetchAll();
}

function count_all_notifications(array $filters = []): int {
    $whereConditions = [];
    $params = [];
    
    if (!empty($filters['search'])) {
        $whereConditions[] = 'n.message LIKE ?';
        $params[] = "%{$filters['search']}%";
    }
    
    if (!empty($filters['type'])) {
        $whereConditions[] = 'n.type = ?';
        $params[] = $filters['type'];
    }
    
    if (isset($filters['is_read'])) {
        $whereConditions[] = 'n.is_read = ?';
        $params[] = $filters['is_read'] ? 1 : 0;
    }
    
    if (!empty($filters['user_id'])) {
        $whereConditions[] = 'n.user_id = ?';
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $whereConditions[] = 'DATE(n.created_at) >= ?';
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $whereConditions[] = 'DATE(n.created_at) <= ?';
        $params[] = $filters['date_to'];
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    return (int)db_query("
        SELECT COUNT(*)
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        {$whereClause}
    ", $params)->fetchColumn();
}

function get_notification_statistics(): array {
    return [
        'total' => (int)db_query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
        'unread' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn(),
        'today' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'this_week' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
        'this_month' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn(),
    ];
}

function get_notification_types(): array {
    return db_query('SELECT DISTINCT type FROM notifications ORDER BY type')->fetchAll(PDO::FETCH_COLUMN);
}

function broadcast_notification_to_all_users(string $message, string $type = 'system', ?int $entityId = null): int {
    $users = db_query('SELECT id FROM users WHERE is_active = 1')->fetchAll();
    $count = 0;
    
    foreach ($users as $user) {
        add_notification((int)$user['id'], $type, $entityId, $message);
        $count++;
    }
    
    return $count;
}

function mark_all_notifications_as_read(): int {
    return db_query('UPDATE notifications SET is_read = 1 WHERE is_read = 0')->rowCount();
}

function delete_old_notifications(int $days = 30): int {
    return db_query('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])->rowCount();
}

function delete_notification_by_id(int $notificationId): bool {
    return db_query('DELETE FROM notifications WHERE id = ?', [$notificationId])->rowCount() > 0;
}

function bulk_update_notifications(array $notificationIds, array $updates): int {
    if (empty($notificationIds) || empty($updates)) {
        return 0;
    }
    
    $setParts = [];
    $params = [];
    
    if (isset($updates['is_read'])) {
        $setParts[] = 'is_read = ?';
        $params[] = $updates['is_read'] ? 1 : 0;
    }
    
    if (empty($setParts)) {
        return 0;
    }
    
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    $params = array_merge($params, $notificationIds);
    
    $setClause = implode(', ', $setParts);
    
    return db_query("UPDATE notifications SET {$setClause} WHERE id IN ({$placeholders})", $params)->rowCount();
}

function bulk_delete_notifications(array $notificationIds): int {
    if (empty($notificationIds)) {
        return 0;
    }
    
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    
    return db_query("DELETE FROM notifications WHERE id IN ({$placeholders})", $notificationIds)->rowCount();
}
