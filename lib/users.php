<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../config.php';

function get_user_by_id(int $userId): ?array {
	$user = db_query('SELECT * FROM users WHERE id = ?', [$userId])->fetch();
	return $user ?: null;
}



function profile_url(array $user): string {
	$id = isset($user['id']) ? (int)$user['id'] : 0;
	$name = isset($user['name']) ? (string)$user['name'] : '';
	$map = [ 'ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss' ];
	$slug = strtr(trim($name), $map);
	$slug = strtolower($slug);
	$slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
	$slug = trim((string)$slug, '-');
	return '/profile/' . ($slug !== '' ? $slug : 'user-' . (int)$user['id']);
}

function update_user_profile(int $userId, array $fields, ?array $avatarFile = null, bool $deleteAvatar = false): ?array {
	$allowedKeys = ['name','user_titel','bio','blog_url','website_url','instagram_url','twitter_url','facebook_url','tiktok_url','youtube_url'];
	$sets = [];
	$params = [];
	$avatarPath = null;
	
	foreach ($allowedKeys as $k) {
		if (array_key_exists($k, $fields)) {
			$sets[] = "$k = ?";
			$params[] = $fields[$k];
		}
	}
	

	
	// Handle avatar deletion
	if ($deleteAvatar) {
		$sets[] = 'avatar_path = NULL';
		$avatarPath = null;
	} elseif ($avatarFile && isset($avatarFile['error']) && $avatarFile['error'] === UPLOAD_ERR_OK) {
		$ext = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
		if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) {
			$basename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
			$dest = rtrim(UPLOAD_DIR, '/') . '/' . $basename;
			if (move_uploaded_file($avatarFile['tmp_name'], $dest)) {
				$sets[] = 'avatar_path = ?';
				$avatarPath = 'uploads/' . $basename;
				$params[] = $avatarPath;
			}
		}
	}
	
	if (!$sets) return null;
	$params[] = $userId;
	db_query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
	
	// Return updated user data for session update
	return [
		'name' => $fields['name'] ?? null,
		'avatar_path' => $avatarPath
	];
}

function toggle_follow(int $followerId, int $followeeId): array {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    try {
        $exists = db_query('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?', [$followerId, $followeeId])->fetchColumn();
        if ($exists) {
            db_query('DELETE FROM follows WHERE follower_id = ? AND followee_id = ?', [$followerId, $followeeId]);
            $following = false;
        } else {
            db_query('INSERT INTO follows (follower_id, followee_id) VALUES (?,?)', [$followerId, $followeeId]);
            $following = true;
        }
        $followersCount = get_followers_count($followeeId);
        $followingCount = get_following_count($followerId);
        $pdo->commit();
        return ['ok' => true, 'following' => $following, 'followersCount' => $followersCount, 'followingCount' => $followingCount];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => 'Error toggling follow'];
    }
}

function is_following(int $followerId, int $followeeId): bool {
    return (bool)db_query('SELECT 1 FROM follows WHERE follower_id = ? AND followee_id = ?', [$followerId, $followeeId])->fetchColumn();
}

function get_followers_count(int $userId): int {
    return (int)db_query('SELECT COUNT(*) FROM follows WHERE followee_id = ?', [$userId])->fetchColumn();
}

function get_following_count(int $userId): int {
	return (int)db_query('SELECT COUNT(*) FROM follows WHERE follower_id = ?', [$userId])->fetchColumn();
}

function get_following_users(int $userId, int $limit = 20, int $offset = 0): array {
	$stmt = db_query('
		SELECT u.id, u.name, u.avatar_path, u.bio 
		FROM users u 
		JOIN follows f ON f.followee_id = u.id 
		WHERE f.follower_id = ? 
		ORDER BY f.created_at DESC 
		LIMIT ? OFFSET ?
	', [$userId, $limit, $offset]);
	return $stmt->fetchAll();
}

function get_followers_users(int $userId, int $limit = 20, int $offset = 0): array {
	$stmt = db_query('
		SELECT u.id, u.name, u.avatar_path, u.bio 
		FROM users u 
		JOIN follows f ON f.follower_id = u.id 
		WHERE f.followee_id = ? 
		ORDER BY f.created_at DESC 
		LIMIT ? OFFSET ?
	', [$userId, $limit, $offset]);
	return $stmt->fetchAll();
}

function delete_user_by_id(int $userId): bool {
    return (bool)db_query('DELETE FROM users WHERE id = ?', [$userId]);
}

// Admin functions for user management

/**
 * Get all users with pagination and search
 */
function get_all_users(int $limit = 20, int $offset = 0, string $search = '', string $orderBy = 'created_at', string $orderDir = 'DESC'): array {
    $allowedOrderBy = ['id', 'name', 'email', 'created_at', 'is_admin'];
    $allowedOrderDir = ['ASC', 'DESC'];
    
    if (!in_array($orderBy, $allowedOrderBy)) $orderBy = 'created_at';
    if (!in_array($orderDir, $allowedOrderDir)) $orderDir = 'DESC';
    
    $sql = 'SELECT id, name, email, avatar_path, bio, is_admin, email_verified_at, created_at FROM users';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE name LIKE ? OR email LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY $orderBy $orderDir LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    return db_query($sql, $params)->fetchAll();
}

/**
 * Count total users (with optional search)
 */
function count_all_users(string $search = ''): int {
    $sql = 'SELECT COUNT(*) FROM users';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE name LIKE ? OR email LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm];
    }
    
    return (int)db_query($sql, $params)->fetchColumn();
}

/**
 * Toggle admin status for a user
 */
function toggle_user_admin(int $userId): bool {
    try {
        $currentStatus = db_query('SELECT is_admin FROM users WHERE id = ?', [$userId])->fetchColumn();
        $newStatus = $currentStatus ? 0 : 1;
        db_query('UPDATE users SET is_admin = ? WHERE id = ?', [$newStatus, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Error toggling admin status for user $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user details (admin function)
 */
function admin_update_user(int $userId, array $data): bool {
    try {
        $allowedFields = ['name', 'email', 'bio', 'is_admin'];
        $sets = [];
        $params = [];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($sets)) return false;
        
        $params[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        db_query($sql, $params);
        return true;
    } catch (Exception $e) {
        error_log("Error updating user $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user statistics
 */
function get_user_stats(): array {
    try {
        $totalUsers = (int)db_query('SELECT COUNT(*) FROM users')->fetchColumn();
        $adminUsers = (int)db_query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn();
        $verifiedUsers = (int)db_query('SELECT COUNT(*) FROM users WHERE email_verified_at IS NOT NULL')->fetchColumn();
        $recentUsers = (int)db_query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
        
        return [
            'total' => $totalUsers,
            'admins' => $adminUsers,
            'verified' => $verifiedUsers,
            'recent' => $recentUsers
        ];
    } catch (Exception $e) {
        error_log("Error getting user stats: " . $e->getMessage());
        return ['total' => 0, 'admins' => 0, 'verified' => 0, 'recent' => 0];
    }
}

/**
 * Safely delete user (with all related data)
 */
function admin_delete_user(int $userId): bool {
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    
    try {
        // Delete user's recipes and related data (cascading deletes will handle most)
        db_query('DELETE FROM recipes WHERE user_id = ?', [$userId]);
        
        // Delete user
        db_query('DELETE FROM users WHERE id = ?', [$userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error deleting user $userId: " . $e->getMessage());
        return false;
    }
}

