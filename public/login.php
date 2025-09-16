<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

start_session_if_needed();
$error = '';
csrf_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_validate_request()) {
		$error = 'Ungültiges CSRF-Token';
	} else {
		$res = login_user($_POST['email'] ?? '', $_POST['password'] ?? '');
		if ($res['ok']) {
			header('Location: /index.php?message=Welcome!');
			exit;
		} else {
			$error = $res['error'];
		}
	}
}


?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <form method="post" class="bg-white w-full max-w-sm border rounded-lg p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <h1 class="text-xl font-semibold">Login</h1>
            <?php if ($error): ?>
                <div class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
    <div class="text-emerald-700 mb-4">Profil wurde erfolgreich gelöscht.</div>
<?php endif; ?>
            <div>
                <label class="text-sm">E-Mail</label>
                <input type="email" name="email" required class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="text-sm">Passwort</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required class="mt-1 w-full border rounded px-3 py-2 pr-10" />
                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePasswordVisibility()">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                </div>
            </div>
            <script>
                function togglePasswordVisibility() {
                    const passwordField = document.getElementById('password');
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                    } else {
                        passwordField.type = 'password';
                    }
                }
            </script>
            <button class="w-full bg-emerald-600 text-white py-2 rounded">Anmelden</button>
            <div class="text-sm text-center mt-2">
                <a class="text-blue-600 hover:underline" href="/forgot_password.php">Passwort vergessen?</a>
            </div>
            <div class="text-sm text-center">Noch kein Konto? <a class="text-blue-600" href="/register.php">Registrieren</a></div>
        </form>
    </div>
</body>
</html>