<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

$error = '';
csrf_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_repeat = $_POST['password_repeat'] ?? '';
        $terms_accepted = isset($_POST['terms_accepted']);

        // Validierung
        if (empty($name)) {
            $error = 'Name ist erforderlich';
        } elseif (empty($email)) {
            $error = 'E-Mail ist erforderlich';
        } elseif (empty($password)) {
            $error = 'Passwort ist erforderlich';
        } elseif ($password !== $password_repeat) {
            $error = 'Passwörter stimmen nicht überein';
        } elseif (!$terms_accepted) {
            $error = 'Sie müssen den Nutzungsbedingungen zustimmen';
        } else {
            $res = register_user($name, $email, $password);
            if ($res['ok']) {
                $_SESSION['email_for_otp'] = $email;
                header('Location: /verify_otp.php');
                exit;
            } else {
                $error = $res['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrieren - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>

<body class="flex items-center justify-center">
    <div class="flex items-center justify-center w-full min-h-screen shadow-[var(--shadow-6)]">
        <div class="min-h-screen lg:min-h-[calc(100vh-50px)] min-w-full lg:min-w-[450px] bg-center bg-no-repeat bg-cover bg-[var(--rh-primary)] bg-[url(/images/register.jpg)] bg-blend-multiply hidden lg:flex items-end"><a class="text-sm text-[var(--rh-text-black:)]" href="https://www.pexels.com/de-de/foto/person-hande-fotografie-technologie-4109955/" target="_blank">Foto von Polina Tankilevitch</a></div>
        <div class="min-h-screen lg:min-h-[calc(100vh-50px)] min-w-full lg:min-w-[450px] bg-white text-[var(--rh-text-black)] flex justify-center items-start relative max-h-screen">
            <div class="absolute top-0 right-0">
                <a href="/">
                    <i class="fa-solid fa-xmark fa-xl m-3"></i>
                </a>
            </div>
            <form method="post" class="w-full max-w-sm p-6 space-y-4 flex flex-col justify-center">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <h1 class="text-xl font-semibold">Registrieren</h1>
                <?php if ($error): ?>
                    <div class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div>
                    <input type="text" name="name" placeholder="Max Muster/ChefMax" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" />
                </div>
                <div>
                    <input type="email" name="email" placeholder="E-Mail" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" />
                </div>
                <div>
                    <div class="relative">
                        <input type="password" name="password" id="password" placeholder="Passwort" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" onkeyup="checkPasswordStrength(); checkPasswordMatch();" />
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePasswordVisibility('password')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div>
                    <div class="relative">
                        <input type="password" name="password_repeat" id="password_repeat" placeholder="Passwort wiederholen" required class="my-2 w-full border rounded px-3 py-2 appearance-none focus:outline-[var(--rh-primary)]" onkeyup="checkPasswordMatch();" />
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePasswordVisibility('password_repeat')">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                    </div>
                    <div id="password-match-feedback" class="mt-1 text-sm"></div>
                </div>
                <div id="password-strength-feedback" class="mt-2 text-sm space-y-1">
                    <p id="length-check" class="text-red-500 flex items-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg> Mindestens 8 Zeichen</p>
                    <p id="special-char-check" class="text-red-500 flex items-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg> Mindestens zwei Sonderzeichen</p>
                </div>
                <div class="flex items-start space-x-2">
                    <input type="checkbox" name="terms_accepted" id="terms_accepted" required class="mt-1 h-4 w-4 text-emerald-600 border-gray-300 rounded focus:ring-emerald-500" />
                    <label for="terms_accepted" class="text-sm text-gray-700">
                        Ich stimme den <a href="/terms.php" class="text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)] " target="_blank">Nutzungsbedingungen</a> zu *
                    </label>
                </div>
                <script>
                    function togglePasswordVisibility(fieldId) {
                        const passwordField = document.getElementById(fieldId);
                        if (passwordField.type === 'password') {
                            passwordField.type = 'text';
                        } else {
                            passwordField.type = 'password';
                        }
                    }

                    function checkPasswordStrength() {
                        const passwordField = document.getElementById('password');
                        const password = passwordField.value;

                        const lengthCheck = document.getElementById('length-check');
                        const specialCharCheck = document.getElementById('special-char-check');
                        const specialCharRegex = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?~`]/g;

                        // Check length
                        if (password.length >= 8) {
                            lengthCheck.classList.remove('text-red-500');
                            lengthCheck.classList.add('text-green-500');
                            lengthCheck.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Mindestens 8 Zeichen';
                        } else {
                            lengthCheck.classList.remove('text-green-500');
                            lengthCheck.classList.add('text-red-500');
                            lengthCheck.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Mindestens 8 Zeichen';
                        }

                        // Check special characters
                        const specialCharMatches = password.match(specialCharRegex);
                        if (specialCharMatches && specialCharMatches.length >= 2) {
                            specialCharCheck.classList.remove('text-red-500');
                            specialCharCheck.classList.add('text-green-500');
                            specialCharCheck.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Mindestens zwei Sonderzeichen';
                        } else {
                            specialCharCheck.classList.remove('text-green-500');
                            specialCharCheck.classList.add('text-red-500');
                            specialCharCheck.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Mindestens zwei Sonderzeichen';
                        }
                    }

                    function checkPasswordMatch() {
                        const password = document.getElementById('password').value;
                        const passwordRepeat = document.getElementById('password_repeat').value;
                        const feedback = document.getElementById('password-match-feedback');

                        if (passwordRepeat === '') {
                            feedback.innerHTML = '';
                            return;
                        }

                        if (password === passwordRepeat) {
                            feedback.innerHTML = '<span class="text-green-500 flex items-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg> Passwörter stimmen überein</span>';
                        } else {
                            feedback.innerHTML = '<span class="text-red-500 flex items-center"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg> Passwörter stimmen nicht überein</span>';
                        }
                    }
                </script>
                <button class="my-2 block w-full px-4 py-3 rounded bg-blue-600 text-white text-center text-sm font-medium hover:bg-blue-700 transition-colors">Registrieren</button>
                <div class="text-sm text-center">Schon ein Konto? <a class="text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)] " href="/login.php">Login</a></div>
            </form>
        </div>
    </div>
    <script src="/assets/fonts/fontawesome/js/all.min.js"></script>
</body>

</html>