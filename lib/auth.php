<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/mailer.php';
require_once __DIR__ . '/logger.php';

function start_session_if_needed(): void {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
}

function current_user(): ?array {
	start_session_if_needed();
	
	// Wenn bereits eingeloggt, User zurückgeben
	if (isset($_SESSION['user'])) {
		return $_SESSION['user'];
	}
	
	// Remember Me Token prüfen
	if (isset($_COOKIE['remember_token'])) {
		$token = $_COOKIE['remember_token'];
		$user = db_query('SELECT u.* FROM users u 
			JOIN remember_tokens rt ON u.id = rt.user_id 
			WHERE rt.token = ? AND rt.expires_at > NOW()', [$token])->fetch();
		
		if ($user) {
			// User automatisch einloggen
			$_SESSION['user'] = [
				'id' => (int)$user['id'],
				'name' => $user['name'],
				'email' => $user['email'],
				'avatar_path' => $user['avatar_path'] ?? null
			];
			return $_SESSION['user'];
		} else {
			// Ungültiger Token, Cookie löschen
			setcookie('remember_token', '', time() - 3600, '/', '', true, true);
		}
	}
	
	return null;
}

function require_login(): void {
	if (!current_user()) {
		header('Location: /login.php');
		exit;
	}
}

function register_user(string $name, string $email, string $password): array {
	$email = trim(strtolower($email));
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		return ['ok' => false, 'error' => 'Ungültige E-Mail-Adresse'];
	}
	if (strlen($password) < 6) {
		return ['ok' => false, 'error' => 'Passwort muss mindestens 6 Zeichen lang sein'];
	}
	try {
		$hash = password_hash($password, PASSWORD_DEFAULT);
		$otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
		$otp_expires = date('Y-m-d H:i:s', time() + 60 * 15); // OTP valid for 15 minutes
		$result = db_query('INSERT INTO users (name, email, password_hash, otp_code, otp_expires_at) VALUES (?,?,?,?,?)', [$name, $email, $hash, $otp_code, $otp_expires]);
		$userId = db_connection()->lastInsertId();
		$ok = send_mail($email, 'Dein Verifizierungscode', '<p>Dein einmaliger Verifizierungscode ist: <strong>' . htmlspecialchars($otp_code) . '</strong></p><p>Dieser Code ist 15 Minuten gültig.</p>');
		
		// Log registration
		log_user_registration($userId, $name);
		
		return ['ok' => true, 'mail_sent' => $ok];
	} catch (PDOException $e) {
		if ($e->errorInfo[1] === 1062) {
			return ['ok' => false, 'error' => 'E-Mail bereits registriert'];
		}
		return ['ok' => false, 'error' => 'Registrierung fehlgeschlagen'];
	}
}

function login_user(string $email, string $password, bool $remember_me = false): array {
	start_session_if_needed();
	$email = trim(strtolower($email));
	$user = db_query('SELECT * FROM users WHERE email = ?', [$email])->fetch();
	if (!$user || !password_verify($password, $user['password_hash'])) {
		// Log failed login attempt
		log_failed_login($email);
		return ['ok' => false, 'error' => 'E-Mail oder Passwort falsch'];
	}
	if ($user['email_verified_at'] === null) {
		return ['ok' => false, 'error' => 'E-Mail ist noch nicht bestätigt. Bitte überprüfen Sie Ihre E-Mails für den Verifizierungscode.'];
	}
	$_SESSION['user'] = [
		'id' => (int)$user['id'],
		'name' => $user['name'],
		'email' => $user['email'],
		'avatar_path' => $user['avatar_path'] ?? null
	];
	
	// Remember Me Token erstellen
	if ($remember_me) {
		create_remember_token((int)$user['id']);
	}
	
	// Log successful login
	log_user_login((int)$user['id'], $user['name']);
	
	return ['ok' => true];
}

function send_password_reset_email(string $email): array {
    $email = trim(strtolower($email));
    $user = db_query('SELECT id FROM users WHERE email = ?', [$email])->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'Kein Benutzer mit dieser E-Mail-Adresse gefunden.'];
    }

    $token = bin2hex(random_bytes(32)); // Generate a random token
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // Token valid for 1 hour

    db_query('UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?', [
        $token, $expiresAt, (int)$user['id']
    ]);

    // Construct the full reset link - if BASE_URL is empty, use current server info
    if (empty(BASE_URL)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host . '/';
    } else {
        $baseUrl = rtrim(BASE_URL, '/') . '/';
    }
    $resetLink = $baseUrl . 'reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);
    $subject = 'Passwort zurücksetzen für ' . APP_NAME;
    $body = '<p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts erhalten.</p>'
          . '<p>Um Ihr Passwort zurückzusetzen, klicken Sie bitte auf den folgenden Link:</p>'
          . '<p><a href="' . htmlspecialchars($resetLink) . '">Passwort zurücksetzen</a></p>'
          . '<p>Dieser Link ist 1 Stunde gültig.</p>'
          . '<p>Wenn Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail bitte.</p>';

    $mailSent = send_mail($email, $subject, $body);
    return ['ok' => $mailSent, 'error' => $mailSent ? '' : 'E-Mail konnte nicht gesendet werden.'];
}

function verify_password_reset_token(string $email, string $token): bool {
    $user = db_query('SELECT * FROM users WHERE email = ? AND password_reset_token = ? AND password_reset_expires_at > NOW()', [
        $email, $token
    ])->fetch();
    return (bool)$user;
}

function resend_otp_email(string $email): array {
    $email = trim(strtolower($email));
    
    // Check if user exists and is not verified
    $user = db_query('SELECT id, email_verified_at FROM users WHERE email = ?', [$email])->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'Kein Benutzer mit dieser E-Mail-Adresse gefunden.'];
    }
    
    if ($user['email_verified_at'] !== null) {
        return ['ok' => false, 'error' => 'E-Mail ist bereits verifiziert.'];
    }
    
    // Generate new OTP code
    $otp_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_expires = date('Y-m-d H:i:s', time() + 60 * 15); // OTP valid for 15 minutes
    
    // Update user with new OTP
    db_query('UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE email = ?', [
        $otp_code, $otp_expires, $email
    ]);
    
    // Send new OTP email
    $subject = 'Neuer Verifizierungscode für ' . APP_NAME;
    $body = '<p>Sie haben einen neuen Verifizierungscode angefordert.</p>'
          . '<p>Ihr neuer Verifizierungscode ist: <strong>' . htmlspecialchars($otp_code) . '</strong></p>'
          . '<p>Dieser Code ist 15 Minuten gültig.</p>'
          . '<p>Falls Sie diese Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.</p>';
    
    $mailSent = send_mail($email, $subject, $body);
    
    // Debug information
    if (!$mailSent) {
        error_log("Failed to send OTP email to: $email");
        return ['ok' => false, 'error' => 'E-Mail konnte nicht gesendet werden. Bitte überprüfen Sie die E-Mail-Konfiguration.'];
    }
    
    return ['ok' => true, 'otp_code' => $otp_code]; // Return OTP for debugging
}

function reset_password(string $email, string $token, string $newPassword): array {
    $email = trim(strtolower($email));
    if (strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'Passwort muss mindestens 6 Zeichen lang sein'];
    }
    $pdo = get_db_connection();
    $pdo->beginTransaction();
    try {
        if (!verify_password_reset_token($email, $token)) {
            throw new Exception('Ungültiger oder abgelaufener Token.');
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        db_query('UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE email = ?', [
            $hash, $email
        ]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function logout_user(): void {
	start_session_if_needed();
	
	// Log logout before clearing session
	if (isset($_SESSION['user']['id'])) {
		log_user_logout((int)$_SESSION['user']['id'], $_SESSION['user']['name']);
		clear_remember_token((int)$_SESSION['user']['id']);
	}
	
	$_SESSION = [];
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
	}
	session_destroy();
}

function is_admin(): bool {
    $user = current_user();
    if (!$user) return false;
    
    // Prüfe ob der User Admin ist
    $result = db_query('SELECT is_admin FROM users WHERE id = ?', [(int)$user['id']])->fetch();
    return (bool)($result['is_admin'] ?? false);
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: /');
        exit;
    }
}

function create_remember_token(int $user_id): void {
	// Alte Tokens für diesen User löschen
	db_query('DELETE FROM remember_tokens WHERE user_id = ?', [$user_id]);
	
	// Neuen Token generieren
	$token = bin2hex(random_bytes(32));
	$expires_at = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 Tage
	
	// Token in Datenbank speichern
	db_query('INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)', 
		[$user_id, $token, $expires_at]);
	
	// Cookie setzen
	setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
}

function clear_remember_token(int $user_id): void {
	// Alle Remember Me Tokens für diesen User löschen
	db_query('DELETE FROM remember_tokens WHERE user_id = ?', [$user_id]);
	
	// Cookie löschen
	setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

