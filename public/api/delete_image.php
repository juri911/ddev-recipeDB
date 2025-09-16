<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/recipes.php';
require_once __DIR__ . '/../../lib/csrf.php';

header('Content-Type: application/json');

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageId = (int)($input['image_id'] ?? 0);
$csrfToken = $input['csrf_token'] ?? '';

if (!csrf_validate_token($csrfToken)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    delete_recipe_image($imageId, $user['id']);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log("Error deleting image: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to delete image']);
}
