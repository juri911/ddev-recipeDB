<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

start_session_if_needed();
$error = null;
csrf_start();

// Gespeicherte E-Mail aus Cookie laden
$saved_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';
$remember_checked = !empty($saved_email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        $res = login_user($_POST['email'] ?? '', $_POST['password'] ?? '', $remember_me);
        if ($res['ok']) {
            // E-Mail für "Angemeldet bleiben" speichern (nur für UX)
            if ($remember_me) {
                setcookie('remember_email', $_POST['email'], time() + (30 * 24 * 60 * 60), '/', '', true, true);
            } else {
                setcookie('remember_email', '', time() - 3600, '/', '', true, true);
            }
            
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
    <style>
        /* Toggle Switch Styling */
        .toggle-checkbox {
            transform: translateX(0);
        }
        .toggle-checkbox:checked {
            transform: translateX(100%);
            border-color: var(--rh-primary);
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: var(--rh-primary);
        }
    </style>
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
            <?php if (!empty($error)): ?>
                <div class="text-red-600 text-md"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="text-emerald-700 mb-4">Profil wurde erfolgreich gelöscht.</div>
            <?php endif; ?>
            <div>
                <input type="email" name="email" placeholder="E-Mail" value="<?php echo htmlspecialchars($saved_email); ?>" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" />
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
            
            <!-- Toggle Switch für "Angemeldet bleiben" -->
            <div class="flex items-center justify-between my-4">
                <span class="text-sm text-gray-700">Angemeldet bleiben</span>
                <div class="relative inline-block w-12 align-middle select-none">
                    <input type="checkbox" name="remember_me" id="remember_me" value="1" <?php echo $remember_checked ? 'checked' : ''; ?> class="toggle-checkbox absolute block w-6 h-6 rounded-full bg-white border-4 appearance-none cursor-pointer transition-all duration-200 ease-in-out" style="right: 1.5rem;" />
                    <label for="remember_me" class="toggle-label block overflow-hidden h-6 rounded-full bg-gray-300 cursor-pointer transition-all duration-200 ease-in-out"></label>
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