<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';

$currentUser = get_current_user();
$selectedCategory = $_GET['category'] ?? null;

csrf_start(); 

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

$csrfToken = csrf_token();

include __DIR__ . '/includes/header.php';
?>

<!-- Categories Section -->
<section class="min-h-screen w-full container-fluid mx-auto lg:px-[50px] mt-[50px]">
    <!-- Page Header -->
    <div class="text-center mb-12">
        <h1 class="text-4xl font-bold text-gray-900 dark:text-white mb-4 flex items-center justify-center gap-3">
            <i class="fas fa-tags text-[#2d7ef7]"></i>
            Kategorien
        </h1>
        <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
            Entdecke Rezepte nach Kategorien und finde genau das, wonach du suchst.
        </p>
    </div>

    <!-- Categories Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-12">
        <?php foreach ($categories as $category): ?>
            <a href="?category=<?= htmlspecialchars(urlencode($category['name'])) ?>" 
               class="group bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600 hover:shadow-xl transition-all duration-300 hover:scale-105 aspect-square flex flex-col items-center justify-center text-center
                      <?= $selectedCategory === $category['name'] ? 'ring-2 ring-[#2d7ef7] bg-blue-50 dark:bg-blue-900/20' : '' ?>">
                <div class="w-16 h-16 flex items-center justify-center rounded-full shadow-lg group-hover:scale-110 transition-transform duration-300 mb-4" 
                     style="background-color: <?= htmlspecialchars($category['color']) ?>">
                    <i class="fas <?= htmlspecialchars($category['icon']) ?> text-white text-2xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white group-hover:text-[#2d7ef7] transition-colors mb-2">
                        <?= htmlspecialchars($category['name']) ?>
                    </h3>
                    <?php if ($category['description']): ?>
                        <p class="text-gray-600 dark:text-gray-400 text-xs leading-tight">
                            <?= htmlspecialchars($category['description']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($selectedCategory): ?>
        <!-- Selected Category Header -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-8 shadow-lg border border-gray-200 dark:border-gray-600 mb-8">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4 flex items-center justify-center gap-3">
                    <i class="fas fa-utensils text-[#2d7ef7]"></i>
                    Rezepte in der Kategorie "<?= htmlspecialchars($selectedCategory) ?>"
                </h2>
                <p class="text-gray-600 dark:text-gray-400">
                    Entdecke alle Rezepte in dieser Kategorie
                </p>
            </div>
        </div>
        
        <?php if (empty($recipes)): ?>
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-12 shadow-lg border border-gray-200 dark:border-gray-600 text-center">
                <i class="fas fa-search text-6xl text-gray-400 dark:text-gray-600 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Keine Rezepte gefunden</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    In dieser Kategorie sind noch keine Rezepte vorhanden.
                </p>
                <a href="/recipe_new.php" class="inline-flex items-center gap-2 px-6 py-3 bg-[#2d7ef7] text-white rounded-lg hover:bg-blue-600 transition-colors">
                    <i class="fas fa-plus"></i>
                    Erstes Rezept erstellen
                </a>
            </div>
        <?php else: ?>
            <!-- Debug Output (temporär) -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="bg-yellow-100 p-4 rounded mb-4">
                    <h3>Debug Info:</h3>
                    <pre><?php print_r($recipes); ?></pre>
                </div>
            <?php endif; ?>
            
            <!-- Recipes Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                <?php foreach ($recipes as $recipe): ?>
                    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-600 overflow-hidden hover:shadow-xl transition-all duration-300 hover:scale-105 aspect-square">
                        <a href="<?= recipe_url($recipe) ?>" class="block h-full flex flex-col">
                            <?php if ($recipe['image_path']): ?>
                                <div class="relative flex-1 overflow-hidden">
                                    <img src="<?= htmlspecialchars(absolute_url_from_path($recipe['image_path'])) ?>" 
                                         alt="<?= htmlspecialchars($recipe['title']) ?>"
                                         class="aspect-square object-cover transition-transform duration-300 hover:scale-110"
                                         onerror="console.log('Image failed to load:', this.src); this.parentElement.innerHTML='<div class=\'flex-1 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center\'><i class=\'fas fa-image text-4xl text-gray-400 dark:text-gray-600\'></i></div>';">
                              </div>
                            <?php else: ?>
                                <div class="flex-1 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 flex items-center justify-center">
                                    <i class="fas fa-image text-4xl text-gray-400 dark:text-gray-600"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="p-4 flex-shrink-0">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2 hover:text-[#2d7ef7] transition-colors line-clamp-2">
                                    <?= htmlspecialchars($recipe['title']) ?>
                                </h3>
                                
                                <div class="flex items-center text-xs text-gray-600 dark:text-gray-400">
                                    <?php if ($recipe['author_avatar_path']): ?>
                                        <img src="<?= htmlspecialchars($recipe['author_avatar_path']) ?>" 
                                             alt="<?= htmlspecialchars($recipe['author_name']) ?>" 
                                             class="w-6 h-6 rounded-full mr-2 outline-2 outline-offset-2 outline-[#2d7ef7]">
                                    <?php else: ?>
                                        <div class="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-600 mr-2 flex items-center justify-center">
                                            <i class="fas fa-user text-gray-600 dark:text-gray-400 text-xs"></i>
                                        </div>
                                    <?php endif; ?>
                                    <span class="font-medium truncate">von <?= htmlspecialchars($recipe['author_name']) ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>