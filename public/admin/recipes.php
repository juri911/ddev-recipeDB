<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/recipes.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/logger.php';

// Session starten
session_start();

// Prüfen ob User eingeloggt ist
require_login();

// Nur Admins haben Zugriff
require_admin();

// Aktueller User
$user = current_user();

$message = '';
$error = null;

// CSRF-Schutz
csrf_start();

// Pagination und Search
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$orderBy = $_GET['order_by'] ?? 'created_at';
$orderDir = $_GET['order_dir'] ?? 'DESC';

$offset = ($page - 1) * $perPage;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $action = $_POST['action'] ?? '';
        $recipeId = (int)($_POST['recipe_id'] ?? 0);
        
        try {
            switch ($action) {
                case 'delete':
                    $recipe = get_recipe_by_id($recipeId);
                    if (admin_delete_recipe($recipeId)) {
                        log_admin_action('recipe_delete', "Rezept '{$recipe['title']}' (ID: {$recipeId}) von Benutzer '{$recipe['author_name']}' gelöscht");
                        $message = 'Rezept wurde erfolgreich gelöscht';
                    } else {
                        throw new Exception('Fehler beim Löschen des Rezepts');
                    }
                    break;
                    
                case 'update':
                    $recipeData = [
                        'title' => trim($_POST['title'] ?? ''),
                        'description' => trim($_POST['description'] ?? ''),
                        'difficulty' => $_POST['difficulty'] ?? 'easy',
                        'duration_minutes' => (int)($_POST['duration_minutes'] ?? 0),
                        'portions' => !empty($_POST['portions']) ? (int)$_POST['portions'] : null,
                        'category' => trim($_POST['category'] ?? '') ?: null
                    ];
                    
                    // Validation
                    if (empty($recipeData['title'])) {
                        throw new Exception('Titel ist erforderlich');
                    }
                    
                    if (empty($recipeData['description'])) {
                        throw new Exception('Beschreibung ist erforderlich');
                    }
                    
                    if (!in_array($recipeData['difficulty'], ['easy', 'medium', 'hard'])) {
                        $recipeData['difficulty'] = 'easy';
                    }
                    
                    $oldRecipe = get_recipe_by_id($recipeId);
                    if (admin_update_recipe($recipeId, $recipeData)) {
                        $changes = [];
                        if ($oldRecipe['title'] !== $recipeData['title']) $changes[] = "Titel: '{$oldRecipe['title']}' → '{$recipeData['title']}'";
                        if ($oldRecipe['description'] !== $recipeData['description']) $changes[] = "Beschreibung geändert";
                        if ($oldRecipe['difficulty'] !== $recipeData['difficulty']) $changes[] = "Schwierigkeit: {$oldRecipe['difficulty']} → {$recipeData['difficulty']}";
                        if ($oldRecipe['duration_minutes'] !== $recipeData['duration_minutes']) $changes[] = "Dauer: {$oldRecipe['duration_minutes']} → {$recipeData['duration_minutes']} Min";
                        if ($oldRecipe['portions'] !== $recipeData['portions']) $changes[] = "Portionen: {$oldRecipe['portions']} → {$recipeData['portions']}";
                        if ($oldRecipe['category'] !== $recipeData['category']) $changes[] = "Kategorie: '{$oldRecipe['category']}' → '{$recipeData['category']}'";
                        
                        $changeText = empty($changes) ? 'Keine Änderungen' : implode(', ', $changes);
                        log_admin_action('recipe_update', "Rezept '{$oldRecipe['title']}' (ID: {$recipeId}) bearbeitet: {$changeText}");
                        $message = 'Rezept wurde erfolgreich aktualisiert';
                    } else {
                        throw new Exception('Fehler beim Aktualisieren des Rezepts');
                    }
                    break;
                    
                default:
                    throw new Exception('Unbekannte Aktion');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get recipes and stats
$recipes = admin_get_all_recipes($perPage, $offset, $search, $orderBy, $orderDir);
$totalRecipes = admin_count_all_recipes($search);
$totalPages = max(1, (int)ceil($totalRecipes / $perPage));
$recipeStats = get_recipe_stats();

// Get categories for dropdown
$categories = db_query('SELECT name FROM recipe_categories ORDER BY sort_order, name')->fetchAll();

// Set page title and CSRF token for header
$pageTitle = 'Rezeptverwaltung - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-3 bg-gradient-to-r from-orange-500 to-red-600 rounded-xl shadow-lg">
                    <i class="fas fa-utensils text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Rezeptverwaltung</h1>
                    <p class="text-slate-600">Verwalte alle Rezepte der Website</p>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-600"></i>
                    <span class="text-green-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                    <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-utensils text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Gesamt</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($recipeStats['total']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-red-100 rounded-lg">
                        <i class="fas fa-heart text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Likes</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($recipeStats['total_likes']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-clock text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Letzte 30 Tage</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($recipeStats['recent']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-stopwatch text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Ø Dauer</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo $recipeStats['avg_duration']; ?> min</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-8">
            <form method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="flex-1">
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Nach Titel, Beschreibung oder Autor suchen..."
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                </div>
                <div class="flex gap-2">
                    <select name="order_by" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                        <option value="created_at" <?php echo $orderBy === 'created_at' ? 'selected' : ''; ?>>Erstellt</option>
                        <option value="title" <?php echo $orderBy === 'title' ? 'selected' : ''; ?>>Titel</option>
                        <option value="author_name" <?php echo $orderBy === 'author_name' ? 'selected' : ''; ?>>Autor</option>
                        <option value="likes_count" <?php echo $orderBy === 'likes_count' ? 'selected' : ''; ?>>Likes</option>
                        <option value="difficulty" <?php echo $orderBy === 'difficulty' ? 'selected' : ''; ?>>Schwierigkeit</option>
                        <option value="duration_minutes" <?php echo $orderBy === 'duration_minutes' ? 'selected' : ''; ?>>Dauer</option>
                    </select>
                    <select name="order_dir" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                        <option value="DESC" <?php echo $orderDir === 'DESC' ? 'selected' : ''; ?>>Absteigend</option>
                        <option value="ASC" <?php echo $orderDir === 'ASC' ? 'selected' : ''; ?>>Aufsteigend</option>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Suchen
                    </button>
                </div>
            </form>
        </div>

        <!-- Recipes Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            <?php if (empty($recipes)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-utensils text-6xl text-slate-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-slate-600 mb-2">Keine Rezepte gefunden</h3>
                    <p class="text-slate-500">Versuche andere Suchbegriffe oder Filter.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden hover:shadow-xl transition-shadow">
                        <!-- Recipe Image -->
                        <div class="aspect-video bg-slate-200 relative">
                            <?php if (!empty($recipe['images'])): ?>
                                <img 
                                    src="<?php echo htmlspecialchars(absolute_url_from_path($recipe['images'][0]['file_path'])); ?>" 
                                    alt="<?php echo htmlspecialchars($recipe['title']); ?>"
                                    class="w-full h-full object-cover"
                                >
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fas fa-image text-slate-400 text-3xl"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Difficulty Badge -->
                            <div class="absolute top-2 left-2">
                                <?php
                                $difficultyColors = [
                                    'easy' => 'bg-green-500',
                                    'medium' => 'bg-yellow-500', 
                                    'hard' => 'bg-red-500'
                                ];
                                $difficultyLabels = [
                                    'easy' => 'Einfach',
                                    'medium' => 'Mittel',
                                    'hard' => 'Schwer'
                                ];
                                ?>
                                <span class="px-2 py-1 text-xs font-medium text-white rounded-full <?php echo $difficultyColors[$recipe['difficulty']]; ?>">
                                    <?php echo $difficultyLabels[$recipe['difficulty']]; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Recipe Content -->
                        <div class="p-4">
                            <h3 class="font-semibold text-slate-800 mb-2 line-clamp-2">
                                <?php echo htmlspecialchars($recipe['title']); ?>
                            </h3>
                            
                            <p class="text-sm text-slate-600 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($recipe['description'], 0, 100)) . (strlen($recipe['description']) > 100 ? '...' : ''); ?>
                            </p>
                            
                            <!-- Author -->
                            <div class="flex items-center gap-2 mb-3">
                                <img 
                                    src="<?php echo htmlspecialchars($recipe['author_avatar_path'] ? absolute_url_from_path($recipe['author_avatar_path']) : '/images/default_avatar.png'); ?>" 
                                    alt="Avatar" 
                                    class="w-6 h-6 rounded-full object-cover"
                                >
                                <span class="text-sm text-slate-600"><?php echo htmlspecialchars($recipe['author_name']); ?></span>
                            </div>
                            
                            <!-- Meta Info -->
                            <div class="flex items-center justify-between text-xs text-slate-500 mb-4">
                                <span><i class="fas fa-clock mr-1"></i><?php echo $recipe['duration_minutes']; ?> min</span>
                                <span><i class="fas fa-heart mr-1"></i><?php echo $recipe['likes_count']; ?></span>
                                <span><?php echo date('d.m.Y', strtotime($recipe['created_at'])); ?></span>
                            </div>
                            
                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <!-- View Recipe -->
                                <a 
                                    href="<?php echo htmlspecialchars(recipe_url($recipe)); ?>" 
                                    target="_blank"
                                    class="flex-1 px-3 py-2 text-center text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 transition-colors text-sm"
                                >
                                    <i class="fas fa-eye mr-1"></i>Ansehen
                                </a>
                                
                                <!-- Edit Recipe -->
                                <button 
                                    onclick="editRecipe(<?php echo htmlspecialchars(json_encode($recipe)); ?>)"
                                    class="px-3 py-2 text-green-600 border border-green-600 rounded-lg hover:bg-green-50 transition-colors text-sm"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <!-- Delete Recipe -->
                                <form method="POST" class="inline" onsubmit="return confirm('Rezept wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!')">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
                                    <button 
                                        type="submit" 
                                        class="px-3 py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm"
                                    >
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center">
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                           class="px-3 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                           class="px-3 py-2 <?php echo $i === $page ? 'bg-orange-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?> rounded-lg transition-colors">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                           class="px-3 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Recipe Modal -->
<div id="editRecipeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Rezept bearbeiten</h3>
                    <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="editRecipeForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="recipe_id" id="editRecipeId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Titel</label>
                            <input type="text" name="title" id="editRecipeTitle" required 
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Beschreibung</label>
                            <textarea name="description" id="editRecipeDescription" rows="4" required 
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Schwierigkeit</label>
                                <select name="difficulty" id="editRecipeDifficulty" 
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                                    <option value="easy">Einfach</option>
                                    <option value="medium">Mittel</option>
                                    <option value="hard">Schwer</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Dauer (Min.)</label>
                                <input type="number" name="duration_minutes" id="editRecipeDuration" min="0" 
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Portionen</label>
                                <input type="number" name="portions" id="editRecipePortions" min="1" 
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Kategorie</label>
                            <select name="category" id="editRecipeCategory" 
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-orange-500">
                                <option value="">Keine Kategorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editRecipe(recipe) {
    document.getElementById('editRecipeId').value = recipe.id;
    document.getElementById('editRecipeTitle').value = recipe.title;
    document.getElementById('editRecipeDescription').value = recipe.description;
    document.getElementById('editRecipeDifficulty').value = recipe.difficulty;
    document.getElementById('editRecipeDuration').value = recipe.duration_minutes;
    document.getElementById('editRecipePortions').value = recipe.portions || '';
    document.getElementById('editRecipeCategory').value = recipe.category || '';
    document.getElementById('editRecipeModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editRecipeModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editRecipeModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
