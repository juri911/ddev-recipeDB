<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/recipes.php';
require_once __DIR__ . '/../../lib/csrf.php';



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
                
                db_query('UPDATE recipe_categories SET name = ?, description = ?, color = ?, icon = ?, sort_order = ? WHERE name = ?',
                    [$name, $description, $color, $icon, $sortOrder, $oldName]);
                
                $message = 'Kategorie wurde aktualisiert';
                
            } elseif ($action === 'delete') {
                // Kategorie löschen
                $name = $_POST['name'] ?? '';
                
                // Prüfe ob Rezepte in dieser Kategorie existieren
                $recipeCount = (int)db_query('SELECT COUNT(*) FROM recipes WHERE category = ?', [$name])->fetchColumn();
                if ($recipeCount > 0) {
                    throw new Exception('Kategorie kann nicht gelöscht werden, da sie noch ' . $recipeCount . ' Rezept(e) enthält');
                }
                
                db_query('DELETE FROM recipe_categories WHERE name = ?', [$name]);
                $message = 'Kategorie wurde gelöscht';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Hole alle Kategorien für die Anzeige
$categories = get_all_categories();

// Hole alle FontAwesome Icons für die Auswahl
$faIcons = [
    'fa-utensils', 'fa-drumstick-bite', 'fa-leaf', 'fa-ice-cream', 'fa-egg', 
    'fa-cookie-bite', 'fa-wine-glass', 'fa-birthday-cake', 'fa-carrot',
    'fa-apple-alt', 'fa-bread-slice', 'fa-cheese', 'fa-fish', 'fa-hamburger',
    'fa-hotdog', 'fa-pizza-slice', 'fa-seedling'
];

$csrfToken = csrf_token();

include __DIR__ . '/../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Kategorien verwalten</h1>
        <button onclick="openModal('createCategoryModal')" 
                class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">
            Neue Kategorie
        </button>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Kategorien-Liste -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Icon</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Beschreibung</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Farbe</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sortierung</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aktionen</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="w-8 h-8 flex items-center justify-center rounded-full" 
                                 style="background-color: <?= htmlspecialchars($category['color']) ?>">
                                <i class="fas <?= htmlspecialchars($category['icon']) ?> text-white"></i>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($category['name']) ?></td>
                        <td class="px-6 py-4"><?= htmlspecialchars($category['description'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded" style="background-color: <?= htmlspecialchars($category['color']) ?>"></div>
                                <?= htmlspecialchars($category['color']) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= (int)$category['sort_order'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button onclick="editCategory(<?= htmlspecialchars(json_encode($category)) ?>)"
                                    class="text-blue-600 hover:text-blue-800 mr-3">
                                Bearbeiten
                            </button>
                            <button onclick="deleteCategory('<?= htmlspecialchars($category['name']) ?>')"
                                    class="text-red-600 hover:text-red-800">
                                Löschen
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Neue Kategorie -->
<div id="createCategoryModal" class="fixed inset-0 bg-black/50 z-50  hidden" role="dialog">
    <div class="min-h-screen px-4 text-center">
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="create">
                
                <h3 class="text-lg font-medium mb-4">Neue Kategorie erstellen</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" required 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                        <textarea name="description" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Farbe</label>
                            <input type="color" name="color" value="#3B82F6" 
                                   class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icon</label>
                            <select name="icon" 
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <?php foreach ($faIcons as $icon): ?>
                                    <option value="<?= htmlspecialchars($icon) ?>">
                                        <?= htmlspecialchars($icon) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sortierung</label>
                        <input type="number" name="sort_order" value="0" min="0" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>
                
                <div class="mt-5 sm:mt-6 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('createCategoryModal')"
                            class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                        Erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kategorie bearbeiten -->
<div id="editCategoryModal" class="fixed inset-0 bg-black/50 z-50  hidden" role="dialog">
    <div class="min-h-screen px-4 text-center">
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="old_name" id="edit_old_name">
                
                <h3 class="text-lg font-medium mb-4">Kategorie bearbeiten</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Name</label>
                        <input type="text" name="name" id="edit_name" required 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                        <textarea name="description" id="edit_description" rows="2"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Farbe</label>
                            <input type="color" name="color" id="edit_color"
                                   class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Icon</label>
                            <select name="icon" id="edit_icon"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <?php foreach ($faIcons as $icon): ?>
                                    <option value="<?= htmlspecialchars($icon) ?>">
                                        <?= htmlspecialchars($icon) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sortierung</label>
                        <input type="number" name="sort_order" id="edit_sort_order" min="0" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>
                
                <div class="mt-5 sm:mt-6 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('editCategoryModal')"
                            class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Kategorie löschen -->
<div id="deleteCategoryModal" class="fixed inset-0 bg-black/50 z-50  hidden" role="dialog">
    <div class="min-h-screen px-4 text-center">
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="post" class="p-6">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="name" id="delete_category_name">
                
                <h3 class="text-lg font-medium mb-4">Kategorie löschen</h3>
                
                <p class="text-sm text-gray-500 mb-4">
                    Sind Sie sicher, dass Sie diese Kategorie löschen möchten? 
                    Dies kann nicht rückgängig gemacht werden.
                </p>
                
                <div class="mt-5 sm:mt-6 flex justify-end gap-2">
                    <button type="button" onclick="closeModal('deleteCategoryModal')"
                            class="px-4 py-2 text-sm border rounded-md hover:bg-gray-50">
                        Abbrechen
                    </button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700">
                        Löschen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

function editCategory(category) {
    document.getElementById('edit_old_name').value = category.name;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description || '';
    document.getElementById('edit_color').value = category.color;
    document.getElementById('edit_icon').value = category.icon;
    document.getElementById('edit_sort_order').value = category.sort_order;
    
    openModal('editCategoryModal');
}

function deleteCategory(name) {
    document.getElementById('delete_category_name').value = name;
    openModal('deleteCategoryModal');
}

// Schließe Modals wenn außerhalb geklickt wird
window.onclick = function(event) {
    if (event.target.classList.contains('fixed')) {
        event.target.classList.add('hidden');
        document.body.style.overflow = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>