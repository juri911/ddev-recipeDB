<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';

header('Content-Type: application/json');

// Debug logging
error_log("Like.php called with method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

$user = current_user();
if (!$user) {
    error_log("No user logged in");
    echo json_encode(['ok' => false, 'redirect' => '/login.php']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// KORRIGIERTE CSRF Validierung
if (!csrf_validate_request()) {
    error_log("CSRF validation failed");
    error_log("Expected token: " . csrf_token());
    error_log("Received token: " . ($_POST['csrf_token'] ?? 'none'));
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$recipeId = (int)($_POST['recipe_id'] ?? 0);
if ($recipeId <= 0) {
    error_log("Invalid recipe ID: " . $recipeId);
    echo json_encode(['ok' => false, 'error' => 'Invalid recipe ID']);
    exit;
}

error_log("Attempting to toggle like for recipe " . $recipeId . " by user " . $user['id']);

try {
    $res = toggle_like($recipeId, (int)$user['id']);
    error_log("Like toggle result: " . print_r($res, true));
    echo json_encode($res);
} catch (Throwable $e) {
    error_log("Error toggling like: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to toggle like: ' . $e->getMessage()]);
}
exit;

