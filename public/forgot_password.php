<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

start_session_if_needed();
$error = null;
$success = null;
csrf_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein.';
        } else {
            $res = send_password_reset_email($email);
            if ($res['ok']) {
                $success = 'Wenn die E-Mail-Adresse in unserem System existiert, haben wir Ihnen einen Link zum Zurücksetzen des Passworts gesendet.';
            } else {
                // Be vague about the error for security reasons
                $success = 'Wenn die E-Mail-Adresse in unserem System existiert, haben wir Ihnen einen Link zum Zurücksetzen des Passworts gesendet.';
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <form method="post" class="bg-white w-full max-w-sm border rounded-lg p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <h1 class="text-xl font-semibold">Passwort vergessen</h1>
            <?php if (!empty($error)): ?>
                <div class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="text-emerald-600 text-sm"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <div>
                <label class="text-sm">E-Mail</label>
                <input type="email" name="email" required class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <button class="w-full bg-emerald-600 text-white py-2 rounded">Link senden</button>
            <div class="text-sm text-center"><a class="text-blue-600" href="/login.php">Zurück zum Login</a></div>
        </form>
    </div>
</body>
</html>
