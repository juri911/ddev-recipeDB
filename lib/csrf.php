<?php
require_once __DIR__ . '/../lib/auth.php';

function csrf_start(): void {
	start_session_if_needed();
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
}

function csrf_token(): string {
	csrf_start();
	return $_SESSION['csrf_token'];
}

function csrf_field(): string {
	$token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
	return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}


function csrf_validate_request(): bool {
    // Hole Token aus verschiedenen Quellen
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    // Für JSON Requests
    if (empty($token)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? '';
    }
    
    return csrf_validate_token($token);
}





function csrf_validate_token(string $token): bool {
    csrf_start();
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_require(): void {
	if (!csrf_validate_request()) {
		http_response_code(403);
		echo 'Ungültiges CSRF-Token.';
		exit;
	}
}


