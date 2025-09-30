<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/notifications.php'; // Added for notifications
require_once __DIR__ . '/../lib/users.php';        // Added for fetching followers

function generate_slug(string $title): string {
    $slug = trim($title);
    $map = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss'
    ];
    $slug = strtr($slug, $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string)$slug, '-');
    return $slug ?? '';
}

function recipe_url(array $recipe): string {
    $id = isset($recipe['id']) ? (int)$recipe['id'] : 0;
    $title = isset($recipe['title']) ? (string)$recipe['title'] : '';
    $slug = generate_slug($title);
    return '/recipe/' . $id . ($slug !== '' ? '-' . $slug : '');
}

function create_recipe(int $userId, string $title, string $description, string $difficulty, int $durationMinutes, array $uploadedFiles = [], array $ingredients = [], array $steps = [], ?int $portions = null, ?string $category = null): int {
	$allowed = ['easy', 'medium', 'hard'];
	if (!in_array($difficulty, $allowed, true)) {
		$difficulty = 'easy';
	}
	$durationMinutes = max(0, $durationMinutes);
	$pdo = get_db_connection();
	$pdo->beginTransaction();
	try {
		db_query('INSERT INTO recipes (user_id, title, description, difficulty, duration_minutes, portions, category) VALUES (?,?,?,?,?,?,?)', [
			$userId, $title, $description, $difficulty, $durationMinutes, $portions, $category
		]);
		$recipeId = (int)$pdo->lastInsertId();

		// Insert ingredients
		$sortOrder = 0;
		foreach ($ingredients as $ingredient) {
			if (empty(trim($ingredient['name']))) continue;
			db_query('INSERT INTO recipe_ingredients (recipe_id, quantity, unit, name, sort_order) VALUES (?,?,?,?,?)', [
				$recipeId, 
				empty($ingredient['quantity']) ? null : (float)$ingredient['quantity'],
				trim($ingredient['unit']) === '' ? null : trim($ingredient['unit']),
				trim($ingredient['name']),
				$sortOrder++
			]);
		}

		// Insert steps
		$sortOrder = 0;
		foreach ($steps as $step) {
			if (empty(trim($step['description']))) continue;
			db_query('INSERT INTO recipe_steps (recipe_id, description, sort_order) VALUES (?,?,?)', [
				$recipeId, 
				trim($step['description']),
				$sortOrder++
			]);
		}

		upload_recipe_images($recipeId, $uploadedFiles);

		// Notify followers
		$followers = db_query('SELECT follower_id FROM follows WHERE followee_id = ?', [$userId])->fetchAll();
		foreach ($followers as $follower) {
			add_notification((int)$follower['follower_id'], 'new_recipe', $recipeId, 'hat ein neues Rezept hinzugefügt: ' . $title);
		}

		$pdo->commit();
		return $recipeId;
	} catch (Throwable $e) {
		$pdo->rollBack();
		throw $e;
	}
}

function update_recipe(
    int $recipeId,
    int $userId,
    string $title,
    string $description,
    string $difficulty,
    int $durationMinutes,
    array $uploadedFiles = [],
    bool $replaceImages = false,
    array $ingredients = [],
    array $steps = [],
    ?int $portions = null,
    ?string $category = null
): void {
    $allowed = ['easy', 'medium', 'hard'];
    if (!in_array($difficulty, $allowed, true)) {
        $difficulty = 'easy';
    }
    $durationMinutes = max(0, $durationMinutes);
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    try {
        // Update recipe main data, now including portions and category
        db_query(
            'UPDATE recipes SET title = ?, description = ?, difficulty = ?, duration_minutes = ?, portions = ?, category = ? WHERE id = ? AND user_id = ?',
            [$title, $description, $difficulty, $durationMinutes, $portions, $category, $recipeId, $userId]
        );

        // Update ingredients
        db_query('DELETE FROM recipe_ingredients WHERE recipe_id = ?', [$recipeId]);
        $sortOrder = 0;
        foreach ($ingredients as $ingredient) {
            if (empty(trim($ingredient['name']))) continue;
            db_query(
                'INSERT INTO recipe_ingredients (recipe_id, quantity, unit, name, sort_order) VALUES (?,?,?,?,?)',
                [
                    $recipeId,
                    empty($ingredient['quantity']) ? null : (float)$ingredient['quantity'],
                    trim($ingredient['unit']) === '' ? null : trim($ingredient['unit']),
                    trim($ingredient['name']),
                    $sortOrder++
                ]
            );
        }

        // Update steps
        db_query('DELETE FROM recipe_steps WHERE recipe_id = ?', [$recipeId]);
        $sortOrder = 0;
        foreach ($steps as $step) {
            if (empty(trim($step['description']))) continue;
            db_query(
                'INSERT INTO recipe_steps (recipe_id, description, sort_order) VALUES (?,?,?)',
                [$recipeId, trim($step['description']), $sortOrder++]
            );
        }

        // Handle images
        if ($replaceImages) {
            $old = db_query('SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY sort_order, id', [$recipeId])->fetchAll();
            db_query('DELETE FROM recipe_images WHERE recipe_id = ?', [$recipeId]);
            foreach ($old as $img) {
                $path = __DIR__ . '/../public/' . ltrim($img['file_path'], '/');
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
        upload_recipe_images($recipeId, $uploadedFiles);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}


function delete_recipe(int $recipeId, int $userId): void {
	$images = db_query('SELECT * FROM recipe_images WHERE recipe_id = ?', [$recipeId])->fetchAll();
	db_query('DELETE FROM recipes WHERE id = ? AND user_id = ?', [$recipeId, $userId]);
	foreach ($images as $img) {
		$path = __DIR__ . '/../public/' . ltrim($img['file_path'], '/');
		if (is_file($path)) {
			@unlink($path);
		}
	}
}

function delete_recipe_image(int $imageId, int $userId): void {
	$pdo = get_db_connection();
	$pdo->beginTransaction();
	try {
		$image = db_query('SELECT ri.*, r.user_id FROM recipe_images ri JOIN recipes r ON ri.recipe_id = r.id WHERE ri.id = ? AND r.user_id = ?', [$imageId, $userId])->fetch();
		if (!$image) {
			throw new Exception('Image not found or not authorized to delete.');
		}

		db_query('DELETE FROM recipe_images WHERE id = ?', [$imageId]);

		$path = __DIR__ . '/../public/' . ltrim($image['file_path'], '/');
		if (is_file($path)) {
			@unlink($path);
		}
		$pdo->commit();
	} catch (Throwable $e) {
		$pdo->rollBack();
		throw $e;
	}
}

function upload_recipe_images(int $recipeId, array $uploadedFiles): void {
	if (!isset($uploadedFiles['name']) || !is_array($uploadedFiles['name'])) {
		return;
	}
	$allowedExt = ['jpg','jpeg','png','gif','webp'];
	for ($i = 0; $i < count($uploadedFiles['name']); $i++) {
		if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) continue;
		$orig = $uploadedFiles['name'][$i];
		$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
		if (!in_array($ext, $allowedExt, true)) continue;
		$basename = bin2hex(random_bytes(8)) . '.' . $ext;
		$destPath = rtrim(UPLOAD_DIR, '/'). '/' . $basename;
		if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $destPath)) {
			db_query('INSERT INTO recipe_images (recipe_id, file_path, sort_order) VALUES (?,?,?)', [
				$recipeId, 'uploads/' . $basename, $i
			]);
		}
	}
}



function get_feed_recipes(int $limit, int $offset, ?string $search = null): array {
    $params = [];
    $where = '';
    if ($search) {
        $where = 'WHERE r.title LIKE ? OR r.description LIKE ?';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $sql = "
        SELECT DISTINCT r.*, 
               u.name AS author_name, 
               u.avatar_path AS author_avatar_path, 
               u.user_titel AS user_titel
        FROM recipes r
        JOIN users u ON r.user_id = u.id
        $where
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    $stmt = db_query($sql, $params);
    $recipes = $stmt->fetchAll();

    foreach ($recipes as &$r) {
        $r['images'] = db_query('SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY sort_order, id', [$r['id']])->fetchAll();
    }
    return $recipes;
}

function count_feed_recipes(?string $search = null): int {
	$params = [];
	$sql = 'SELECT COUNT(*) FROM recipes r';
	if ($search !== null && trim($search) !== '') {
		$sql .= ' WHERE r.title LIKE ? OR r.description LIKE ?';
		$params[] = '%' . $search . '%';
		$params[] = '%' . $search . '%';
	}
	return (int) db_query($sql, $params)->fetchColumn();
}


function get_recipe_by_id(int $recipeId): ?array {
    $recipe = db_query('
        SELECT DISTINCT r.*, 
               u.name AS author_name, 
               u.avatar_path AS author_avatar_path, 
               u.user_titel AS user_titel
        FROM recipes r 
        JOIN users u ON u.id = r.user_id 
        WHERE r.id = ?
    ', [$recipeId])->fetch();
    
    if (!$recipe) return null;
    
    $recipe['images'] = db_query('SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY sort_order, id', [$recipeId])->fetchAll();
    $recipe['ingredients'] = db_query('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY sort_order, id', [$recipeId])->fetchAll();
    $recipe['steps'] = db_query('SELECT * FROM recipe_steps WHERE recipe_id = ? ORDER BY sort_order, id', [$recipeId])->fetchAll();
    return $recipe;
}
function get_user_recipes(int $userId, int $limit = 20, int $offset = 0): array {
	$stmt = db_query('SELECT DISTINCT r.*, u.name AS author_name, u.avatar_path AS author_avatar_path FROM recipes r JOIN users u ON u.id = r.user_id WHERE r.user_id = ? ORDER BY r.created_at DESC LIMIT ? OFFSET ?', [$userId, $limit, $offset]);
	$recipes = $stmt->fetchAll();
	foreach ($recipes as &$r) {
		$r['images'] = db_query('SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY sort_order, id', [$r['id']])->fetchAll();
	}
	return $recipes;
}
function get_user_favorites(int $userId, int $limit = 20, int $offset = 0): array {
	$stmt = db_query('
		SELECT r.*, u.name AS author_name, u.avatar_path AS author_avatar_path 
		FROM recipes r 
		JOIN users u ON u.id = r.user_id 
		JOIN recipe_favorites rf ON rf.recipe_id = r.id 
		WHERE rf.user_id = ? 
		ORDER BY rf.created_at DESC 
		LIMIT ? OFFSET ?
	', [$userId, $limit, $offset]);
	$recipes = $stmt->fetchAll();
	foreach ($recipes as &$r) {
		$r['images'] = db_query('SELECT * FROM recipe_images WHERE recipe_id = ? ORDER BY sort_order, id', [$r['id']])->fetchAll();
	}
	return $recipes;
}


function count_user_recipes(int $userId): int {
	return (int) db_query('SELECT COUNT(*) FROM recipes WHERE user_id = ?', [$userId])->fetchColumn();
}

function toggle_like(int $recipeId, int $userId): array {
	$pdo = get_db_connection();
	$pdo->beginTransaction();
	try {
		$exists = db_query('SELECT 1 FROM recipe_likes WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId])->fetchColumn();
		if ($exists) {
			db_query('DELETE FROM recipe_likes WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId]);
			db_query('UPDATE recipes SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = ?', [$recipeId]);
			$liked = false;
		} else {
			db_query('INSERT INTO recipe_likes (recipe_id, user_id) VALUES (?,?)', [$recipeId, $userId]);
			db_query('UPDATE recipes SET likes_count = likes_count + 1 WHERE id = ?', [$recipeId]);
			$liked = true;
		}
		$likes = (int) db_query('SELECT likes_count FROM recipes WHERE id = ?', [$recipeId])->fetchColumn();
		$pdo->commit();
		return ['ok' => true, 'liked' => $liked, 'likes' => $likes];
	} catch (Throwable $e) {
		$pdo->rollBack();
		return ['ok' => false, 'error' => 'Fehler beim Liken'];
	}
}

// Favorites functions
function toggle_favorite(int $recipeId, int $userId): array {
	$pdo = get_db_connection();
	$pdo->beginTransaction();
	try {
		$exists = db_query('SELECT 1 FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId])->fetchColumn();
		if ($exists) {
			db_query('DELETE FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId]);
			$favorited = false;
		} else {
			db_query('INSERT INTO recipe_favorites (recipe_id, user_id) VALUES (?,?)', [$recipeId, $userId]);
			$favorited = true;
		}
		$pdo->commit();
		return ['ok' => true, 'favorited' => $favorited];
	} catch (Throwable $e) {
		$pdo->rollBack();
		return ['ok' => false, 'error' => 'Fehler beim Favorisieren'];
	}
}

function is_favorited(int $recipeId, int $userId): bool {
	return (bool)db_query('SELECT 1 FROM recipe_favorites WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId])->fetchColumn();
}


function count_user_favorites(int $userId): int {
	return (int)db_query('SELECT COUNT(*) FROM recipe_favorites WHERE user_id = ?', [$userId])->fetchColumn();
}

function is_liked(int $recipeId, int $userId): bool {
	return (bool)db_query('SELECT 1 FROM recipe_likes WHERE recipe_id = ? AND user_id = ?', [$recipeId, $userId])->fetchColumn();
}

// Category functions
function get_all_categories(): array {
	return db_query('SELECT * FROM recipe_categories ORDER BY sort_order, name')->fetchAll();
}

function get_category_by_name(string $name): ?array {
	$result = db_query('SELECT * FROM recipe_categories WHERE name = ?', [$name])->fetch();
	return $result ?: null;
}

function search_recipes(string $query): array {
    // Passe ggf. den Tabellennamen und die Spalten an!
    return db_query('SELECT * FROM recipes WHERE title LIKE ?', ['%' . $query . '%'])->fetchAll();
}


function get_users_who_liked(int $recipeId, int $limit = 5): array {
    return db_query('
        SELECT u.id, u.name, u.avatar_path 
        FROM recipe_likes rl 
        JOIN users u ON rl.user_id = u.id 
        WHERE rl.recipe_id = ? 
        ORDER BY rl.created_at DESC 
        LIMIT ?
    ', [$recipeId, $limit])->fetchAll();
}
