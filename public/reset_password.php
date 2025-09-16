<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

start_session_if_needed();
$error = '';
$success = '';
$email = trim($_GET['email'] ?? '');
$token = trim($_GET['token'] ?? '');
csrf_start();

// Verify token and email immediately
if (!verify_password_reset_token($email, $token)) {
    $error = 'Ungültiger oder abgelaufener Passwort-Reset-Link.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Bitte geben Sie beide Passwörter ein.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwörter stimmen nicht überein.';
        } else {
            $res = reset_password($email, $token, $newPassword);
            if ($res['ok']) {
                $success = 'Ihr Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.';
            } else {
                $error = 'Fehler beim Zurücksetzen des Passworts: ' . ($res['error'] ?? 'Unbekannter Fehler');
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <form method="post" class="bg-white w-full max-w-sm border rounded-lg p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

            <h1 class="text-xl font-semibold">Passwort zurücksetzen</h1>
            <?php if ($error): ?>
                <div class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="text-emerald-600 text-sm"><?php echo htmlspecialchars($success); ?> <a class="text-blue-600" href="/login.php">Zum Login</a></div>
            <?php endif; ?>

            <?php if (empty($error) && empty($success)): // Only show form if no error and not already successful ?>
            <div>
                <label class="text-sm">Neues Passwort</label>
                <input type="password" name="password" required class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="text-sm">Passwort bestätigen</label>
                <input type="password" name="confirm_password" required class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <button class="w-full bg-emerald-600 text-white py-2 rounded">Passwort zurücksetzen</button>
            <?php endif; ?>
            <div class="text-sm text-center"><a class="text-blue-600" href="/login.php">Zurück zum Login</a></div>
        </form>
    </div>
</body>
</html>
