<?php
/**
 * User Activity Logger
 * Logs user activities like login, logout, page visits, etc.
 */

require_once __DIR__ . '/db.php';

/**
 * Log user activity
 */
function log_user_activity($action, $description = null, $user_id = null, $username = null) {
    try {
        // Get current user if not provided
        if ($user_id === null && function_exists('current_user')) {
            $current_user = current_user();
            if ($current_user) {
                $user_id = $current_user['id'];
                $username = $current_user['name'];
            }
        }
        
        // Get IP address
        $ip_address = get_client_ip();
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Get session ID
        $session_id = session_id() ?: null;
        
        // Insert log entry
        db_query(
            'INSERT INTO user_activity_logs (user_id, username, action, description, ip_address, user_agent, session_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$user_id, $username, $action, $description, $ip_address, $user_agent, $session_id]
        );
        
        return true;
    } catch (Exception $e) {
        // Silent fail - don't break the application if logging fails
        error_log("Logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Get user activity logs with pagination and filtering
 */
function get_user_activity_logs($limit = 50, $offset = 0, $search = '', $action_filter = '', $user_filter = '', $date_from = '', $date_to = '') {
    $sql = 'SELECT ual.*, u.name as user_name, u.avatar_path 
            FROM user_activity_logs ual 
            LEFT JOIN users u ON ual.user_id = u.id 
            WHERE 1=1';
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $sql .= ' AND (ual.username LIKE ? OR ual.description LIKE ? OR ual.ip_address LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Action filter
    if (!empty($action_filter)) {
        $sql .= ' AND ual.action = ?';
        $params[] = $action_filter;
    }
    
    // User filter
    if (!empty($user_filter)) {
        $sql .= ' AND (ual.user_id = ? OR ual.username LIKE ?)';
        $params[] = (int)$user_filter;
        $params[] = '%' . $user_filter . '%';
    }
    
    // Date filters
    if (!empty($date_from)) {
        $sql .= ' AND ual.created_at >= ?';
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $sql .= ' AND ual.created_at <= ?';
        $params[] = $date_to . ' 23:59:59';
    }
    
    $sql .= ' ORDER BY ual.created_at DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;
    
    return db_query($sql, $params)->fetchAll();
}

/**
 * Count user activity logs with filtering
 */
function count_user_activity_logs($search = '', $action_filter = '', $user_filter = '', $date_from = '', $date_to = '') {
    $sql = 'SELECT COUNT(*) FROM user_activity_logs ual WHERE 1=1';
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $sql .= ' AND (ual.username LIKE ? OR ual.description LIKE ? OR ual.ip_address LIKE ?)';
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Action filter
    if (!empty($action_filter)) {
        $sql .= ' AND ual.action = ?';
        $params[] = $action_filter;
    }
    
    // User filter
    if (!empty($user_filter)) {
        $sql .= ' AND (ual.user_id = ? OR ual.username LIKE ?)';
        $params[] = (int)$user_filter;
        $params[] = '%' . $user_filter . '%';
    }
    
    // Date filters
    if (!empty($date_from)) {
        $sql .= ' AND ual.created_at >= ?';
        $params[] = $date_from . ' 00:00:00';
    }
    
    if (!empty($date_to)) {
        $sql .= ' AND ual.created_at <= ?';
        $params[] = $date_to . ' 23:59:59';
    }
    
    return (int)db_query($sql, $params)->fetchColumn();
}

/**
 * Get activity statistics
 */
function get_activity_stats() {
    try {
        $stats = [];
        
        // Total logs
        $stats['total_logs'] = (int)db_query('SELECT COUNT(*) FROM user_activity_logs')->fetchColumn();
        
        // Today's activity
        $stats['today_logs'] = (int)db_query('SELECT COUNT(*) FROM user_activity_logs WHERE DATE(created_at) = CURDATE()')->fetchColumn();
        
        // Unique users today
        $stats['unique_users_today'] = (int)db_query('SELECT COUNT(DISTINCT user_id) FROM user_activity_logs WHERE DATE(created_at) = CURDATE() AND user_id IS NOT NULL')->fetchColumn();
        
        // Most active action today
        $most_active = db_query('SELECT action, COUNT(*) as count FROM user_activity_logs WHERE DATE(created_at) = CURDATE() GROUP BY action ORDER BY count DESC LIMIT 1')->fetch();
        $stats['most_active_action'] = $most_active ?: ['action' => 'Keine', 'count' => 0];
        
        // Recent logins (last 24h)
        $stats['recent_logins'] = (int)db_query('SELECT COUNT(*) FROM user_activity_logs WHERE action = "login" AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn();
        
        // Top IP addresses today
        $stats['top_ips'] = db_query('SELECT ip_address, COUNT(*) as count FROM user_activity_logs WHERE DATE(created_at) = CURDATE() AND ip_address IS NOT NULL GROUP BY ip_address ORDER BY count DESC LIMIT 5')->fetchAll();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting activity stats: " . $e->getMessage());
        return [
            'total_logs' => 0,
            'today_logs' => 0,
            'unique_users_today' => 0,
            'most_active_action' => ['action' => 'Fehler', 'count' => 0],
            'recent_logins' => 0,
            'top_ips' => []
        ];
    }
}

/**
 * Get available actions for filtering
 */
function get_available_actions() {
    try {
        return db_query('SELECT DISTINCT action FROM user_activity_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Clean old logs (older than specified days)
 */
function clean_old_logs($days = 90) {
    try {
        $deleted = db_query('DELETE FROM user_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])->rowCount();
        log_user_activity('system', "Alte Logs bereinigt: {$deleted} Einträge gelöscht (älter als {$days} Tage)");
        return $deleted;
    } catch (Exception $e) {
        error_log("Error cleaning old logs: " . $e->getMessage());
        return false;
    }
}

/**
 * Log page visit
 */
function log_page_visit($page_name = null) {
    if ($page_name === null) {
        $page_name = $_SERVER['REQUEST_URI'] ?? 'unknown';
    }
    
    log_user_activity('page_visit', "Besuch: {$page_name}");
}

/**
 * Log user login
 */
function log_user_login($user_id, $username) {
    log_user_activity('login', 'Benutzer hat sich eingeloggt', $user_id, $username);
}

/**
 * Log user logout
 */
function log_user_logout($user_id = null, $username = null) {
    log_user_activity('logout', 'Benutzer hat sich ausgeloggt', $user_id, $username);
}

/**
 * Log failed login attempt
 */
function log_failed_login($username_attempt) {
    log_user_activity('login_failed', "Fehlgeschlagener Login-Versuch für: {$username_attempt}");
}

/**
 * Log user registration
 */
function log_user_registration($user_id, $username) {
    log_user_activity('registration', 'Neuer Benutzer registriert', $user_id, $username);
}

/**
 * Log admin action
 */
function log_admin_action($action, $description, $user_id = null, $username = null) {
    log_user_activity('admin_' . $action, $description, $user_id, $username);
}
