<?php
require_once __DIR__ . '/../lib/db.php';

function add_comment(int $recipeId, int $userId, string $content, ?int $parentId = null): void {
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('Leerer Kommentar');
    }
    if (mb_strlen($content) > 2000) {
        throw new InvalidArgumentException('Kommentar zu lang');
    }
    
    // Wenn es eine Antwort ist, prüfe ob der Kommentar existiert und zum Rezept gehört
    if ($parentId !== null) {
        $parentComment = db_query(
            'SELECT recipe_id FROM recipe_comments WHERE id = ?', 
            [$parentId]
        )->fetch();
        
        if (!$parentComment || $parentComment['recipe_id'] !== $recipeId) {
            throw new InvalidArgumentException('Ungültiger übergeordneter Kommentar');
        }
    }
    
    db_query(
        'INSERT INTO recipe_comments (recipe_id, user_id, content, parent_id) VALUES (?,?,?,?)', 
        [$recipeId, $userId, $content, $parentId]
    );
}

function list_comments(int $recipeId, int $limit = 50, int $offset = 0): array {
    // Hole alle Hauptkommentare (ohne parent_id) mit Autor und Antworten
    $comments = db_query('
        SELECT 
            c.*, 
            u.name AS author_name,
            u.avatar_path AS author_avatar_path,
            r.user_id AS recipe_author_id
        FROM recipe_comments c 
        JOIN users u ON u.id = c.user_id
        JOIN recipes r ON r.id = c.recipe_id
        WHERE c.recipe_id = ? AND c.parent_id IS NULL 
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?', 
        [$recipeId, $limit, $offset]
    )->fetchAll();
    
    // Hole für jeden Hauptkommentar die Antworten
    foreach ($comments as &$comment) {
        $comment['replies'] = db_query('
            SELECT 
                c.*, 
                u.name AS author_name,
                u.avatar_path AS author_avatar_path
            FROM recipe_comments c 
            JOIN users u ON u.id = c.user_id
            WHERE c.parent_id = ? 
            ORDER BY c.created_at ASC', 
            [$comment['id']]
        )->fetchAll();
    }
    
    return $comments;
}

function count_comments(int $recipeId): int {
	return (int) db_query('SELECT COUNT(*) FROM recipe_comments WHERE recipe_id = ?', [$recipeId])->fetchColumn();
}

// Admin functions for comment management

/**
 * Get all comments with pagination and search (admin function)
 */
function admin_get_all_comments(int $limit = 20, int $offset = 0, string $search = '', string $orderBy = 'created_at', string $orderDir = 'DESC'): array {
    $allowedOrderBy = ['id', 'created_at', 'author_name', 'recipe_title'];
    $allowedOrderDir = ['ASC', 'DESC'];
    
    if (!in_array($orderBy, $allowedOrderBy)) $orderBy = 'created_at';
    if (!in_array($orderDir, $allowedOrderDir)) $orderDir = 'DESC';
    
    $sql = 'SELECT c.*, u.name AS author_name, u.avatar_path AS author_avatar_path, r.title AS recipe_title, r.id AS recipe_id 
            FROM recipe_comments c 
            JOIN users u ON u.id = c.user_id 
            JOIN recipes r ON r.id = c.recipe_id';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE c.content LIKE ? OR u.name LIKE ? OR r.title LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY $orderBy $orderDir LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    return db_query($sql, $params)->fetchAll();
}

/**
 * Count all comments (with optional search)
 */
function admin_count_all_comments(string $search = ''): int {
    $sql = 'SELECT COUNT(*) FROM recipe_comments c JOIN users u ON u.id = c.user_id JOIN recipes r ON r.id = c.recipe_id';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE c.content LIKE ? OR u.name LIKE ? OR r.title LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    return (int)db_query($sql, $params)->fetchColumn();
}

/**
 * Admin delete comment
 */
function admin_delete_comment(int $commentId): bool {
    try {
        // Delete comment and all replies (if any)
        db_query('DELETE FROM recipe_comments WHERE id = ? OR parent_id = ?', [$commentId, $commentId]);
        return true;
    } catch (Exception $e) {
        error_log("Error deleting comment $commentId: " . $e->getMessage());
        return false;
    }
}

/**
 * Get comment statistics
 */
function get_comment_stats(): array {
    try {
        $totalComments = (int)db_query('SELECT COUNT(*) FROM recipe_comments')->fetchColumn();
        $recentComments = (int)db_query('SELECT COUNT(*) FROM recipe_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
        $topCommenters = db_query('
            SELECT u.name, COUNT(*) as comment_count 
            FROM recipe_comments c 
            JOIN users u ON u.id = c.user_id 
            GROUP BY c.user_id, u.name 
            ORDER BY comment_count DESC 
            LIMIT 5
        ')->fetchAll();
        $avgCommentsPerRecipe = (float)db_query('
            SELECT AVG(comment_count) FROM (
                SELECT COUNT(*) as comment_count 
                FROM recipe_comments 
                GROUP BY recipe_id
            ) as recipe_comments_count
        ')->fetchColumn();
        
        return [
            'total' => $totalComments,
            'recent' => $recentComments,
            'top_commenters' => $topCommenters,
            'avg_per_recipe' => round($avgCommentsPerRecipe, 1)
        ];
    } catch (Exception $e) {
        error_log("Error getting comment stats: " . $e->getMessage());
        return ['total' => 0, 'recent' => 0, 'top_commenters' => [], 'avg_per_recipe' => 0];
    }
}

/**
 * Admin update comment
 */
function admin_update_comment(int $commentId, string $content): bool {
    try {
        $content = trim($content);
        if (empty($content)) {
            return false;
        }
        
        if (mb_strlen($content) > 2000) {
            return false;
        }
        
        db_query('UPDATE recipe_comments SET content = ? WHERE id = ?', [$content, $commentId]);
        return true;
    } catch (Exception $e) {
        error_log("Error updating comment $commentId: " . $e->getMessage());
        return false;
    }
}


