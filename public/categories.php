<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';

$currentUser = get_current_user();
$selectedCategory = $_GET['category'] ?? null;

// Hole alle Kategorien
$categories = get_all_categories();

// Hole Rezepte der ausgewählten Kategorie
$recipes = [];
if ($selectedCategory) {
    $sql = "
        SELECT r.id,
               r.title,
               u.name AS author_name, 
               u.avatar_path AS author_avatar_path,
               (SELECT file_path 
                FROM recipe_images ri 
                WHERE ri.recipe_id = r.id 
                ORDER BY ri.sort_order, ri.id 
                LIMIT 1) as image_path
        FROM recipes r
        JOIN users u ON r.user_id = u.id
        WHERE r.category = ?
        ORDER BY r.created_at DESC
    ";
    $recipes = db_query($sql, [$selectedCategory])->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Kategorien</h1>

    <!-- Kategorien-Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <?php foreach ($categories as $category): ?>
            <a href="?category=<?= htmlspecialchars(urlencode($category['name'])) ?>" 
               class="block p-6 rounded-lg shadow hover:shadow-lg transition-shadow 
                      <?= $selectedCategory === $category['name'] ? 'bg-blue-100' : 'bg-white' ?>">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 flex items-center justify-center rounded-full" 
                         style="background-color: <?= htmlspecialchars($category['color']) ?>">
                        <i class="fas <?= htmlspecialchars($category['icon']) ?> text-white text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold"><?= htmlspecialchars($category['name']) ?></h3>
                        <?php if ($category['description']): ?>
                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($category['description']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($selectedCategory): ?>
        <h2 class="text-2xl font-bold mb-6">
            Rezepte in der Kategorie "<?= htmlspecialchars($selectedCategory) ?>"
        </h2>
        
        <?php if (empty($recipes)): ?>
            <p class="text-gray-600">Keine Rezepte in dieser Kategorie gefunden.</p>
        <?php else: ?>
            <!-- Rezepte-Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($recipes as $recipe): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <a href="<?= recipe_url($recipe) ?>" class="block">
                            <?php if ($recipe['image_path']): ?>
                                <img src="/<?= htmlspecialchars(ltrim($recipe['image_path'], '/')) ?>" 
                                     alt="<?= htmlspecialchars($recipe['title']) ?>"
                                     class="w-full h-48 object-cover">
                            <?php else: ?>
                                <div class="w-full h-48 bg-gray-100 flex items-center justify-center text-gray-400">
                                    <i class="fas fa-image text-3xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-4">
                                <h3 class="text-xl font-semibold mb-2 hover:text-blue-600">
                                    <?= htmlspecialchars($recipe['title']) ?>
                                </h3>
                                
                                <div class="flex items-center text-sm text-gray-600">
                                    <?php if ($recipe['author_avatar_path']): ?>
                                        <img src="<?= htmlspecialchars($recipe['author_avatar_path']) ?>" 
                                             alt="<?= htmlspecialchars($recipe['author_name']) ?>" 
                                             class="w-6 h-6 rounded-full mr-2">
                                    <?php endif; ?>
                                    <span>von <?= htmlspecialchars($recipe['author_name']) ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>