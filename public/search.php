<?php
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../config.php';

// Hier müssen Sie wahrscheinlich zusätzliche Dateien einbinden
// die die current_user() Funktion enthalten. Zum Beispiel:
require_once __DIR__ . '/../lib/auth.php'; // oder eine ähnliche Datei

// Session starten falls noch nicht geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Benutzer laden
$user = current_user();

$query = trim($_GET['q'] ?? '');
$results = [];
$searched = false;

if ($query !== '') {
    $results = search_recipes($query); // Diese Funktion muss in lib/recipes.php existieren!
    $searched = true;
}

$pageTitle = 'Suche - ' . APP_NAME;
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4">
    <h1 class="text-2xl font-bold mb-6">Suche</h1>

    <?php if ($searched): ?>
        <?php if (empty($results)): ?>
            <div class="text-red-600 mb-6">Keine Ergebnisse gefunden für <b><?php echo htmlspecialchars($query); ?></b>.</div>
        <?php else: ?>
            <div class="text-green-700 mb-6"><?php echo count($results); ?> Ergebnis(se) für <b><?php echo htmlspecialchars($query); ?></b>:</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($results as $r): ?>
                    <a href="<?php echo htmlspecialchars(recipe_url($r)); ?>" class="block bg-white border rounded p-4 hover:shadow">
                        <?php if (!empty($r['images']) && is_array($r['images'])): ?>
                            <img src="/<?php echo htmlspecialchars($r['images'][0]['file_path']); ?>" class="w-full h-32 object-cover mb-2 rounded" alt="Bild">
                        <?php endif; ?>
                        <div class="font-semibold mb-2"><?php echo htmlspecialchars($r['title']); ?></div>
                        <!-- Optional: Weitere Infos -->
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="text-gray-500 mb-6">Bitte gib einen Suchbegriff ein.</div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>