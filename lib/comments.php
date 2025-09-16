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


