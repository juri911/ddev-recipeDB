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


?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
     <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="flex items-center justify-center">
<div class="flex items-center justify-center w-full min-h-screen shadow-[var(--shadow-6)]">   
    <div class="min-h-screen lg:min-h-[calc(100vh-50px)] min-w-full lg:min-w-[450px] bg-center bg-no-repeat bg-cover bg-[var(--rh-primary)] bg-[url(/images/login.jpg)] bg-blend-multiply hidden lg:flex items-end"><a class="text-sm text-[var(--rh-text-black:)]" href="https://www.pexels.com/de-de/foto/luxurioses-nigiri-sushi-mit-frischem-truffel-30682879/" target="_blank">Foto von Airam Dato-on</a></div>
    <div class="min-h-screen lg:min-h-[calc(100vh-50px)] min-w-full lg:min-w-[450px] bg-white text-[var(--rh-text-black)] flex justify-center items-start relative max-h-screen">
        <div class="absolute top-0 right-0">
            <a href="/">
                <i class="fa-solid fa-xmark fa-xl m-3"></i>
            </a>
        </div>
        <form method="post" class="w-full max-w-sm p-6 space-y-4 flex flex-col justify-center">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <h2 class="text-2xl font-semibold">Log In</h2>
            <?php if ($error): ?>
                <div class="text-red-600 text-md"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="text-emerald-700 mb-4">Profil wurde erfolgreich gelöscht.</div>
            <?php endif; ?>
            <div>
                <input type="email" name="email" placeholder="E-Mail" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" />
            </div>
            <div>
                <div class="relative">
                    <input type="password" name="password" id="password" placeholder="Passwort" required class="my-2 w-full border rounded px-3 py-2 pr-10 appearance-none focus:outline-[var(--rh-primary)]" />
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
            <div class="relative">
            <button class="my-2 block w-full px-4 py-3 rounded bg-blue-600 text-white text-center text-sm font-medium hover:bg-blue-700 transition-colors">Anmelden</button>
            <div class="text-sm text-center mt-6 mb-4">
                <a class="text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)] " href="/forgot_password.php">Passwort vergessen?</a>
            </div>
            <div class="text-sm text-center">Noch kein Konto? <a class="text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)] " href="/register.php">Registrieren</a></div>
            </div>
        </form>
    </div>
</div>
<script src="/assets/fonts/fontawesome/js/all.min.js"></script>
</body>
</html>