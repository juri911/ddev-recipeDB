<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../config.php';

$user = current_user();
csrf_start();
$pageTitle = 'Beispielseite';
$csrfToken = csrf_token();

include __DIR__ . '/../includes/header.php';
?>

        <section class="bg-white border rounded-lg p-4">
            <h1 class="text-xl font-semibold mb-2">Beispielseite</h1>
            <p class="text-sm text-gray-700">Dies ist eine Beispielseite im Ordner <code>/public/sites</code>. Füge hier deine Inhalte hinzu.</p>
        </section>

<?php
include __DIR__ . '/../includes/footer.php';
?>


