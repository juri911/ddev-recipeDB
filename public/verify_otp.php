<?php
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config.php';

$error = null;
$message = '';

csrf_start();

// Handle email input and OTP request
if (isset($_POST['request_otp']) && !empty($_POST['email'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $inputEmail = trim($_POST['email']);
        
        // Check if user exists and is not verified
        $user = db_query('SELECT id, email_verified_at FROM users WHERE email = ?', [$inputEmail])->fetch();
        
        if (!$user) {
            $error = 'Kein Benutzer mit dieser E-Mail-Adresse gefunden.';
        } elseif ($user['email_verified_at'] !== null) {
            $error = 'Diese E-Mail-Adresse ist bereits verifiziert.';
        } else {
            // Send OTP email
            $resendResult = resend_otp_email($inputEmail);
            if ($resendResult['ok']) {
                $message = 'Ein neuer Verifizierungscode wurde an ' . htmlspecialchars($inputEmail) . ' gesendet.';
                // Debug: OTP-Code anzeigen (nur für Entwicklung)
                if (isset($resendResult['otp_code'])) {
                    $message .= ' (Debug: OTP-Code: ' . $resendResult['otp_code'] . ')';
                }
                // Store email in session for OTP verification
                $_SESSION['email_for_otp'] = $inputEmail;
                $email = $inputEmail;
            } else {
                $error = $resendResult['error'];
            }
        }
    }
}

// Handle resend OTP request
if (isset($_POST['resend_otp'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } elseif (isset($_SESSION['email_for_otp'])) {
        $email = $_SESSION['email_for_otp'];
        $resendResult = resend_otp_email($email);
        if ($resendResult['ok']) {
            $message = 'Ein neuer Verifizierungscode wurde an Ihre E-Mail-Adresse gesendet.';
            // Debug: OTP-Code anzeigen (nur für Entwicklung)
            if (isset($resendResult['otp_code'])) {
                $message .= ' (Debug: OTP-Code: ' . $resendResult['otp_code'] . ')';
            }
        } else {
            $error = $resendResult['error'];
        }
    } else {
        $error = 'Bitte geben Sie zuerst Ihre E-Mail-Adresse ein.';
    }
}

// Handle change email request
if (isset($_POST['change_email'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        unset($_SESSION['email_for_otp']);
        $email = '';
        $message = 'Sie können jetzt eine andere E-Mail-Adresse eingeben.';
    }
}

// Get email from session or form
$email = $_SESSION['email_for_otp'] ?? '';

// Handle OTP verification
if (isset($_POST['verify_otp']) && !empty($_POST['otp_code'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } elseif (empty($email)) {
        $error = 'Bitte geben Sie zuerst Ihre E-Mail-Adresse ein.';
    } else {
        $otp_code = trim($_POST['otp_code']);

        if (empty($otp_code)) {
            $error = 'Bitte geben Sie den OTP-Code ein.';
        } else {
            // Check OTP in database
            $user = db_query('SELECT * FROM users WHERE email = ? AND otp_code = ? AND otp_expires_at > NOW()', [$email, $otp_code])->fetch();

            if ($user) {
                // OTP is valid, verify email
                db_query('UPDATE users SET email_verified_at = NOW(), otp_code = NULL, otp_expires_at = NULL WHERE id = ?', [$user['id']]);
                unset($_SESSION['email_for_otp']);
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'avatar_path' => $user['avatar_path'] ?? null
                ];
                header('Location: /'); // Redirect to homepage or dashboard
                exit;
            } else {
                $error = 'Ungültiger oder abgelaufener OTP-Code.';
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verifizierung - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-md border rounded-lg p-6 space-y-4">
            <h1 class="text-xl font-semibold text-center">OTP Verifizierung</h1>
            <?php if ($message): ?>
                <div class="text-green-600 text-sm bg-green-50 border border-green-200 rounded p-3"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="text-red-600 text-sm bg-red-50 border border-red-200 rounded p-3"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (empty($email)): ?>
                <!-- E-Mail-Eingabe Formular -->
                <div class="space-y-4">
                    <p class="text-sm text-gray-700 text-center">
                        Geben Sie Ihre E-Mail-Adresse ein, um einen Verifizierungscode zu erhalten.
                    </p>
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div>
                            <label class="text-sm font-medium text-gray-700">E-Mail-Adresse</label>
                            <input type="email" name="email" required 
                                   class="mt-1 w-full border rounded px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   placeholder="ihre-email@example.com" />
                        </div>
                        <button type="submit" name="request_otp" 
                                class="w-full bg-emerald-600 text-white py-2 rounded hover:bg-emerald-700 transition-colors">
                            Verifizierungscode anfordern
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- OTP-Verifizierung Formular -->
                <div class="space-y-4">
                    <p class="text-sm text-gray-700 text-center">
                        Wir haben einen Verifizierungscode an <br><strong><?php echo htmlspecialchars($email); ?></strong><br> gesendet.<br>
                        Falls Sie die E-Mail nicht finden können, könnte sie im Spam-Ordner gelandet sein. Bitte schauen Sie dort nach.<br>
                        Falls Sie keine E-Mail erhalten haben, können Sie einen neuen Code anfordern.
                    </p>
                    
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div>
                            <label class="text-sm font-medium text-gray-700">OTP Code</label>
                            <input type="text" name="otp_code" required 
                                   class="mt-1 w-full border rounded px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" 
                                   maxlength="6" pattern="[0-9]{6}" 
                                   title="Bitte geben Sie einen 6-stelligen Code ein."
                                   placeholder="123456" />
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="verify_otp" 
                                    class="flex-1 bg-emerald-600 text-white py-2 rounded hover:bg-emerald-700 transition-colors">
                                Verifizieren
                            </button>
                            <button type="button" id="resend-btn" 
                                    class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 transition-colors">
                                Erneut senden
                            </button>
                        </div>
                    </form>
                    
                    <!-- E-Mail-Adresse ändern -->
                    <div class="text-center">
                        <button type="button" id="change-email-btn" 
                                class="text-sm text-emerald-600 hover:text-emerald-700 underline">
                            Andere E-Mail-Adresse verwenden
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <script>
        // Resend OTP button
        document.getElementById('resend-btn')?.addEventListener('click', function() {
            if (confirm('Möchten Sie wirklich einen neuen Code anfordern?')) {
                // Create a form data object
                const formData = new FormData();
                formData.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>');
                formData.append('resend_otp', '1');
                
                // Send the request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Reload the page to show the success message
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Fehler beim Senden der Anfrage. Bitte versuchen Sie es erneut.');
                });
            }
        });

        // Change email button
        document.getElementById('change-email-btn')?.addEventListener('click', function() {
            if (confirm('Möchten Sie eine andere E-Mail-Adresse verwenden?')) {
                // Clear the email from session and reload page
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'csrf_token=<?php echo htmlspecialchars(csrf_token()); ?>&change_email=1'
                })
                .then(response => response.text())
                .then(html => {
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    window.location.reload();
                });
            }
        });
    </script>
</body>
</html>
