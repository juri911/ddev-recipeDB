<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate_request()) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $recipe = get_recipe_by_id($id);
    if ($recipe && (int)$recipe['user_id'] === (int)$user['id']) {
        delete_recipe($id, $user['id']);
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
exit;

