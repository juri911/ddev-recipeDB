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
$orderBy = $_GET['order_by'] ?? 'sort_order';
$orderDir = $_GET['order_dir'] ?? 'ASC';

$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'create') {
                // Neue Kategorie erstellen
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#3B82F6');
                $icon = trim($_POST['icon'] ?? 'fa-utensils');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if ($name === '') {
                    throw new Exception('Name ist erforderlich');
                }
                
                db_query('INSERT INTO recipe_categories (name, description, color, icon, sort_order) VALUES (?, ?, ?, ?, ?)', 
                    [$name, $description, $color, $icon, $sortOrder]);
                
                log_admin_action('category_create', "Kategorie '{$name}' erstellt (Farbe: {$color}, Icon: {$icon}, Sortierung: {$sortOrder})");
                $message = 'Kategorie wurde erstellt';
                
            } elseif ($action === 'update') {
                // Kategorie aktualisieren
                $oldName = $_POST['old_name'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $color = trim($_POST['color'] ?? '#3B82F6');
                $icon = trim($_POST['icon'] ?? 'fa-utensils');
                $sortOrder = (int)($_POST['sort_order'] ?? 0);
                
                if ($name === '') {
                    throw new Exception('Name ist erforderlich');
                }
                
                // Get old category for comparison
                $oldCategory = db_query('SELECT * FROM recipe_categories WHERE name = ?', [$oldName])->fetch();
                
                db_query('UPDATE recipe_categories SET name = ?, description = ?, color = ?, icon = ?, sort_order = ? WHERE name = ?',
                    [$name, $description, $color, $icon, $sortOrder, $oldName]);
                
                $changes = [];
                if ($oldCategory['name'] !== $name) $changes[] = "Name: '{$oldCategory['name']}' → '{$name}'";
                if ($oldCategory['description'] !== $description) $changes[] = "Beschreibung geändert";
                if ($oldCategory['color'] !== $color) $changes[] = "Farbe: {$oldCategory['color']} → {$color}";
                if ($oldCategory['icon'] !== $icon) $changes[] = "Icon: {$oldCategory['icon']} → {$icon}";
                if ($oldCategory['sort_order'] !== $sortOrder) $changes[] = "Sortierung: {$oldCategory['sort_order']} → {$sortOrder}";
                
                $changeText = empty($changes) ? 'Keine Änderungen' : implode(', ', $changes);
                log_admin_action('category_update', "Kategorie '{$oldCategory['name']}' bearbeitet: {$changeText}");
                $message = 'Kategorie wurde aktualisiert';
                
            } elseif ($action === 'delete') {
                // Kategorie löschen
                $name = $_POST['name'] ?? '';
                
                // Prüfe ob Rezepte in dieser Kategorie existieren
                $recipeCount = (int)db_query('SELECT COUNT(*) FROM recipes WHERE category = ?', [$name])->fetchColumn();
                if ($recipeCount > 0) {
                    throw new Exception('Kategorie kann nicht gelöscht werden, da sie noch ' . $recipeCount . ' Rezept(e) enthält');
                }
                
                // Get category details before deletion
                $category = db_query('SELECT * FROM recipe_categories WHERE name = ?', [$name])->fetch();
                
                db_query('DELETE FROM recipe_categories WHERE name = ?', [$name]);
                log_admin_action('category_delete', "Kategorie '{$name}' gelöscht (Farbe: {$category['color']}, Icon: {$category['icon']})");
                $message = 'Kategorie wurde gelöscht';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get categories with pagination and search
try {
    $sql = 'SELECT * FROM recipe_categories';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE name LIKE ? OR description LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm];
    }
    
    // Add ordering
    $validOrderBy = ['name', 'description', 'sort_order', 'created_at'];
    $validOrderDir = ['ASC', 'DESC'];
    
    if (in_array($orderBy, $validOrderBy) && in_array($orderDir, $validOrderDir)) {
        $sql .= " ORDER BY {$orderBy} {$orderDir}";
    } else {
        $sql .= " ORDER BY sort_order ASC";
    }
    
    // Count total for pagination
    $countSql = 'SELECT COUNT(*) FROM recipe_categories';
    if (!empty($search)) {
        $countSql .= ' WHERE name LIKE ? OR description LIKE ?';
        $totalCategories = (int)db_query($countSql, [$searchTerm, $searchTerm])->fetchColumn();
    } else {
        $totalCategories = (int)db_query($countSql)->fetchColumn();
    }
    
    // Add pagination
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $categories = db_query($sql, $params)->fetchAll();
    $totalPages = max(1, (int)ceil($totalCategories / $perPage));
    
    // Get category stats
    $categoryStats = [
        'total' => $totalCategories,
        'with_recipes' => (int)db_query('SELECT COUNT(DISTINCT category) FROM recipes WHERE category IS NOT NULL AND category != ""')->fetchColumn(),
        'most_used' => db_query('SELECT category, COUNT(*) as count FROM recipes WHERE category IS NOT NULL AND category != "" GROUP BY category ORDER BY count DESC LIMIT 1')->fetch()
    ];
    
} catch (Exception $e) {
    $error = "Fehler beim Laden der Kategorien: " . $e->getMessage();
    $categories = [];
    $totalCategories = 0;
    $totalPages = 1;
    $categoryStats = ['total' => 0, 'with_recipes' => 0, 'most_used' => null];
}

// Hole alle FontAwesome Icons für die Auswahl
$faIcons = [
    'fa-utensils', 'fa-drumstick-bite', 'fa-leaf', 'fa-ice-cream', 'fa-egg', 
    'fa-cookie-bite', 'fa-wine-glass', 'fa-birthday-cake', 'fa-carrot',
    'fa-apple-alt', 'fa-bread-slice', 'fa-cheese', 'fa-fish', 'fa-hamburger',
    'fa-hotdog', 'fa-pizza-slice', 'fa-seedling', 'fa-coffee', 'fa-candy-cane'
];

// Set page title and CSRF token for header
$pageTitle = 'Kategorienverwaltung - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

include __DIR__ . '/../includes/header.php';
?>

    <div class="min-h-screen bg-slate-50">
        <!-- Header Section -->
        <div class="bg-white border-b border-slate-200 shadow-sm">
            <div class="container mx-auto px-6 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 mb-2">Kategorienverwaltung</h1>
                        <p class="text-slate-600">Verwalte Rezeptkategorien, Icons und Sortierung</p>
                    </div>
                    <button onclick="openModal('createCategoryModal')" 
                            class="inline-flex items-center gap-2 px-6 py-3 bg-[var(--rh-primary)] hover:bg-[var(--rh-primary-hover)] text-white font-medium rounded-lg shadow-lg hover:shadow-xl transition-all duration-200">
                        <i class="fas fa-plus"></i>
                        <span>Neue Kategorie</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-6 py-8">
            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="mb-6">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-check-circle text-emerald-600"></i>
                            <span class="text-emerald-800 font-medium"><?php echo htmlspecialchars($message); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                            <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-tags text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Gesamt Kategorien</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($categoryStats['total']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-utensils text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Mit Rezepten</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($categoryStats['with_recipes']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-star text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Beliebteste</p>
                            <p class="text-lg font-bold text-slate-800">
                                <?php echo $categoryStats['most_used'] ? htmlspecialchars($categoryStats['most_used']['category']) : 'Keine'; ?>
                            </p>
                            <?php if ($categoryStats['most_used']): ?>
                                <p class="text-xs text-slate-500"><?php echo $categoryStats['most_used']['count']; ?> Rezepte</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-8">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <input type="text" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Kategorien durchsuchen..." 
                               class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                    </div>
                    <div class="flex gap-2">
                        <select name="order_by" class="px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="sort_order" <?php echo $orderBy === 'sort_order' ? 'selected' : ''; ?>>Sortierung</option>
                            <option value="name" <?php echo $orderBy === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="created_at" <?php echo $orderBy === 'created_at' ? 'selected' : ''; ?>>Erstellt</option>
                        </select>
                        <select name="order_dir" class="px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                            <option value="ASC" <?php echo $orderDir === 'ASC' ? 'selected' : ''; ?>>Aufsteigend</option>
                            <option value="DESC" <?php echo $orderDir === 'DESC' ? 'selected' : ''; ?>>Absteigend</option>
                        </select>
                        <button type="submit" 
                                class="px-6 py-3 bg-slate-600 hover:bg-slate-700 text-white rounded-lg transition-colors">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full text-center py-12">
                        <i class="fas fa-tags text-6xl text-slate-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-slate-600 mb-2">Keine Kategorien gefunden</h3>
                        <p class="text-slate-500">Erstelle deine erste Kategorie oder versuche andere Suchbegriffe.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <?php 
                        // Get recipe count for this category
                        $recipeCount = (int)db_query('SELECT COUNT(*) FROM recipes WHERE category = ?', [$category['name']])->fetchColumn();
                        ?>
                        <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden hover:shadow-xl transition-shadow">
                            <!-- Category Header -->
                            <div class="p-6 border-b border-slate-100">
                                <div class="flex items-center gap-4 mb-3">
                                    <div class="w-12 h-12 flex items-center justify-center rounded-xl shadow-lg" 
                                         style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($category['icon']); ?> text-white text-lg"></i>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-bold text-slate-800 text-lg"><?php echo htmlspecialchars($category['name']); ?></h3>
                                        <p class="text-slate-600 text-sm"><?php echo $recipeCount; ?> Rezept<?php echo $recipeCount !== 1 ? 'e' : ''; ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                                            #<?php echo (int)$category['sort_order']; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($category['description'])): ?>
                                    <p class="text-slate-600 text-sm leading-relaxed">
                                        <?php echo htmlspecialchars($category['description']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Category Info -->
                            <div class="p-4 bg-slate-50">
                                <div class="flex items-center justify-between text-xs text-slate-500 mb-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-4 h-4 rounded" style="background-color: <?php echo htmlspecialchars($category['color']); ?>"></div>
                                        <span><?php echo htmlspecialchars($category['color']); ?></span>
                                    </div>
                                    <span>Sortierung: <?php echo (int)$category['sort_order']; ?></span>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button onclick="editCategory(<?php echo htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8'); ?>)"
                                            class="flex-1 px-3 py-2 text-center text-emerald-600 border border-emerald-600 rounded-lg hover:bg-emerald-50 transition-colors text-sm font-medium">
                                        <i class="fas fa-edit mr-1"></i>Bearbeiten
                                    </button>
                                    <button onclick="deleteCategory('<?php echo htmlspecialchars($category['name']); ?>')"
                                            class="px-3 py-2 text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors text-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                               class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                               class="px-4 py-2 <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'text-slate-600 border border-slate-300 hover:bg-slate-50'; ?> rounded-lg transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                               class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Modal: Neue Kategorie -->
<div id="createCategoryModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="create">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-slate-200">
                <h3 class="text-xl font-semibold text-slate-800">Neue Kategorie erstellen</h3>
                <button type="button" onclick="closeModal('createCategoryModal')"
                        class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                    <input type="text" name="name" required 
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                           placeholder="z.B. Hauptgerichte">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Beschreibung</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                              placeholder="Kurze Beschreibung der Kategorie..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Farbe</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="color" value="#3B82F6" 
                                   class="w-12 h-12 border border-slate-300 rounded-lg cursor-pointer">
                            <span class="text-sm text-slate-600">Wähle eine Farbe</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Icon</label>
                        <select name="icon" 
                                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                            <?php foreach ($faIcons as $icon): ?>
                                <option value="<?php echo htmlspecialchars($icon); ?>">
                                    <?php echo htmlspecialchars($icon); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Sortierung</label>
                    <input type="number" name="sort_order" value="0" min="0" 
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"
                           placeholder="0">
                    <p class="text-xs text-slate-500 mt-1">Niedrigere Zahlen werden zuerst angezeigt</p>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-200">
                <button type="button" onclick="closeModal('createCategoryModal')"
                        class="px-6 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium">
                    <i class="fas fa-plus mr-2"></i>Erstellen
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Kategorie bearbeiten -->
<div id="editCategoryModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="old_name" id="edit_old_name">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-slate-200">
                <h3 class="text-xl font-semibold text-slate-800">Kategorie bearbeiten</h3>
                <button type="button" onclick="closeModal('editCategoryModal')"
                        class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                    <input type="text" name="name" id="edit_name" required 
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Beschreibung</label>
                    <textarea name="description" id="edit_description" rows="3"
                              class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Farbe</label>
                        <div class="flex items-center gap-2">
                            <input type="color" name="color" id="edit_color"
                                   class="w-12 h-12 border border-slate-300 rounded-lg cursor-pointer">
                            <span class="text-sm text-slate-600">Wähle eine Farbe</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Icon</label>
                        <select name="icon" id="edit_icon"
                                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                            <?php foreach ($faIcons as $icon): ?>
                                <option value="<?php echo htmlspecialchars($icon); ?>">
                                    <?php echo htmlspecialchars($icon); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Sortierung</label>
                    <input type="number" name="sort_order" id="edit_sort_order" min="0" 
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-colors">
                    <p class="text-xs text-slate-500 mt-1">Niedrigere Zahlen werden zuerst angezeigt</p>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-200">
                <button type="button" onclick="closeModal('editCategoryModal')"
                        class="px-6 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-6 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors font-medium">
                    <i class="fas fa-save mr-2"></i>Speichern
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Kategorie löschen -->
<div id="deleteCategoryModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="name" id="delete_category_name">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-slate-200">
                <h3 class="text-xl font-semibold text-slate-800">Kategorie löschen</h3>
                <button type="button" onclick="closeModal('deleteCategoryModal')"
                        class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-6">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-medium text-slate-800">Kategorie wirklich löschen?</h4>
                        <p class="text-sm text-slate-600">Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    </div>
                </div>
                
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-amber-600 mt-0.5"></i>
                        <div class="text-sm text-amber-800">
                            <p class="font-medium mb-1">Wichtiger Hinweis:</p>
                            <p>Kategorien mit zugewiesenen Rezepten können nicht gelöscht werden. Entferne zuerst alle Rezepte aus dieser Kategorie.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-200">
                <button type="button" onclick="closeModal('deleteCategoryModal')"
                        class="px-6 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                    Abbrechen
                </button>
                <button type="submit"
                        class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium">
                    <i class="fas fa-trash mr-2"></i>Löschen
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal functions
    window.openModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Focus first input in modal
            setTimeout(() => {
                const firstInput = modal.querySelector('input[type="text"], textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }, 100);
        }
    };

    window.closeModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };

    window.editCategory = function(category) {
        document.getElementById('edit_old_name').value = category.name;
        document.getElementById('edit_name').value = category.name;
        document.getElementById('edit_description').value = category.description || '';
        document.getElementById('edit_color').value = category.color;
        document.getElementById('edit_icon').value = category.icon;
        document.getElementById('edit_sort_order').value = category.sort_order;
        
        openModal('editCategoryModal');
    };

    window.deleteCategory = function(name) {
        document.getElementById('delete_category_name').value = name;
        openModal('deleteCategoryModal');
    };

    // Close modals when clicking outside or pressing ESC
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('fixed') && event.target.classList.contains('bg-black/50')) {
            const modalId = event.target.id;
            if (modalId) {
                closeModal(modalId);
            }
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const openModals = document.querySelectorAll('.fixed:not(.hidden)');
            openModals.forEach(modal => {
                if (modal.id) {
                    closeModal(modal.id);
                }
            });
        }
    });

    // Color picker preview
    const colorInputs = document.querySelectorAll('input[type="color"]');
    colorInputs.forEach(input => {
        input.addEventListener('change', function() {
            const preview = this.nextElementSibling;
            if (preview && preview.tagName === 'SPAN') {
                preview.textContent = this.value;
            }
        });
    });

    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const nameInput = this.querySelector('input[name="name"]');
            if (nameInput && nameInput.value.trim() === '') {
                e.preventDefault();
                alert('Bitte geben Sie einen Namen für die Kategorie ein.');
                nameInput.focus();
                return false;
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>