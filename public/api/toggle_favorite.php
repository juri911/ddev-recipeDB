<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/recipes.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json');

// Debug logging
error_log("toggle_favorite.php called with method: " . $_SERVER['REQUEST_METHOD']);

$user = current_user();
if (!$user) {
    error_log("No user logged in");
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
error_log("JSON input: " . print_r($input, true));

if (!$input) {
    error_log("Invalid JSON input");
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$recipeId = (int)($input['recipe_id'] ?? 0);
$csrfToken = $input['csrf_token'] ?? '';

error_log("CSRF Token received: " . $csrfToken);
error_log("Session CSRF Token: " . csrf_token());

// KORRIGIERTE CSRF Validierung
if (!csrf_validate_token($csrfToken)) {
    error_log("CSRF validation failed");
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if ($recipeId === 0) {
    error_log("Invalid recipe ID: " . $recipeId);
    echo json_encode(['ok' => false, 'error' => 'Invalid recipe ID']);
    exit;
}

error_log("Attempting to toggle favorite for recipe " . $recipeId . " by user " . $user['id']);

try {
    $result = toggle_favorite($recipeId, (int)$user['id']);
    error_log("Favorite toggle result: " . print_r($result, true));
    echo json_encode($result);
} catch (Throwable $e) {
    error_log("Error toggling favorite: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to toggle favorite: ' . $e->getMessage()]);
}
exit;