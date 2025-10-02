<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/users.php';
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
        $userId = (int)($_POST['user_id'] ?? 0);
        
        try {
            // Prevent admin from modifying themselves in dangerous ways
            if ($userId === (int)$user['id'] && in_array($action, ['delete', 'toggle_admin'])) {
                throw new Exception('Sie können diese Aktion nicht an sich selbst durchführen');
            }
            
            switch ($action) {
                case 'toggle_admin':
                    $targetUser = get_user_by_id($userId);
                    if (toggle_user_admin($userId)) {
                        $newStatus = $targetUser && $targetUser['is_admin'] ? 'entfernt' : 'gewährt';
                        log_admin_action('user_toggle_admin', "Admin-Status {$newStatus} für Benutzer '{$targetUser['name']}' (ID: {$userId})");
                        $message = 'Admin-Status wurde erfolgreich geändert';
                    } else {
                        throw new Exception('Fehler beim Ändern des Admin-Status');
                    }
                    break;
                    
                case 'delete':
                    $targetUser = get_user_by_id($userId);
                    if (admin_delete_user($userId)) {
                        log_admin_action('user_delete', "Benutzer '{$targetUser['name']}' (ID: {$userId}, E-Mail: {$targetUser['email']}) gelöscht");
                        $message = 'Benutzer wurde erfolgreich gelöscht';
                    } else {
                        throw new Exception('Fehler beim Löschen des Benutzers');
                    }
                    break;
                    
                case 'update':
                    $userData = [
                        'name' => trim($_POST['name'] ?? ''),
                        'email' => trim($_POST['email'] ?? ''),
                        'bio' => trim($_POST['bio'] ?? ''),
                        'is_admin' => isset($_POST['is_admin']) ? 1 : 0
                    ];
                    
                    // Validation
                    if (empty($userData['name'])) {
                        throw new Exception('Name ist erforderlich');
                    }
                    
                    if (empty($userData['email']) || !filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Gültige E-Mail-Adresse ist erforderlich');
                    }
                    
                    $targetUser = get_user_by_id($userId);
                    if (admin_update_user($userId, $userData)) {
                        $changes = [];
                        if ($targetUser['name'] !== $userData['name']) $changes[] = "Name: '{$targetUser['name']}' → '{$userData['name']}'";
                        if ($targetUser['email'] !== $userData['email']) $changes[] = "E-Mail: '{$targetUser['email']}' → '{$userData['email']}'";
                        if ($targetUser['bio'] !== $userData['bio']) $changes[] = "Bio geändert";
                        if ((bool)$targetUser['is_admin'] !== (bool)$userData['is_admin']) {
                            $changes[] = "Admin-Status: " . ((bool)$userData['is_admin'] ? 'aktiviert' : 'deaktiviert');
                        }
                        
                        $changeText = empty($changes) ? 'Keine Änderungen' : implode(', ', $changes);
                        log_admin_action('user_update', "Benutzer '{$targetUser['name']}' (ID: {$userId}) bearbeitet: {$changeText}");
                        $message = 'Benutzer wurde erfolgreich aktualisiert';
                    } else {
                        throw new Exception('Fehler beim Aktualisieren des Benutzers');
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

// Get users and stats
$users = get_all_users($perPage, $offset, $search, $orderBy, $orderDir);
$totalUsers = count_all_users($search);
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$userStats = get_user_stats();

// Set page title and CSRF token for header
$pageTitle = 'Benutzerverwaltung - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-3 bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl shadow-lg">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Benutzerverwaltung</h1>
                    <p class="text-slate-600">Verwalte alle registrierten Benutzer</p>
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
                    <div class="p-3 bg-blue-100 rounded-lg">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Gesamt</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($userStats['total']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-crown text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Admins</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($userStats['admins']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-green-100 rounded-lg">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Verifiziert</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($userStats['verified']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-clock text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Letzte 30 Tage</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($userStats['recent']); ?></p>
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
                        placeholder="Nach Name oder E-Mail suchen..."
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
                <div class="flex gap-2">
                    <select name="order_by" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="created_at" <?php echo $orderBy === 'created_at' ? 'selected' : ''; ?>>Registriert</option>
                        <option value="name" <?php echo $orderBy === 'name' ? 'selected' : ''; ?>>Name</option>
                        <option value="email" <?php echo $orderBy === 'email' ? 'selected' : ''; ?>>E-Mail</option>
                        <option value="is_admin" <?php echo $orderBy === 'is_admin' ? 'selected' : ''; ?>>Admin-Status</option>
                    </select>
                    <select name="order_dir" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="DESC" <?php echo $orderDir === 'DESC' ? 'selected' : ''; ?>>Absteigend</option>
                        <option value="ASC" <?php echo $orderDir === 'ASC' ? 'selected' : ''; ?>>Aufsteigend</option>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-search mr-2"></i>Suchen
                    </button>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Benutzer</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">E-Mail</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Registriert</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    <i class="fas fa-users text-4xl mb-2"></i>
                                    <p>Keine Benutzer gefunden</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <img 
                                                src="<?php echo htmlspecialchars($u['avatar_path'] ? absolute_url_from_path($u['avatar_path']) : '/images/default_avatar.png'); ?>" 
                                                alt="Avatar" 
                                                class="w-10 h-10 rounded-full object-cover bg-slate-200"
                                            >
                                            <div>
                                                <div class="font-medium text-slate-900">
                                                    <?php echo htmlspecialchars($u['name']); ?>
                                                    <?php if ($u['is_admin']): ?>
                                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800">
                                                            <i class="fas fa-crown mr-1"></i>Admin
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-slate-500">ID: <?php echo $u['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-slate-900"><?php echo htmlspecialchars($u['email']); ?></div>
                                        <?php if ($u['email_verified_at']): ?>
                                            <div class="text-xs text-green-600">
                                                <i class="fas fa-check-circle mr-1"></i>Verifiziert
                                            </div>
                                        <?php else: ?>
                                            <div class="text-xs text-orange-600">
                                                <i class="fas fa-clock mr-1"></i>Nicht verifiziert
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($u['is_admin']): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                <i class="fas fa-crown mr-1"></i>Administrator
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                <i class="fas fa-user mr-1"></i>Benutzer
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-500">
                                        <?php echo date('d.m.Y H:i', strtotime($u['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <!-- View Profile -->
                                            <a 
                                                href="<?php echo htmlspecialchars(profile_url($u)); ?>" 
                                                target="_blank"
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" 
                                                title="Profil ansehen"
                                            >
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <!-- Edit User -->
                                            <button 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)"
                                                class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors" 
                                                title="Bearbeiten"
                                            >
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Toggle Admin -->
                                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                                <form method="POST" class="inline" onsubmit="return confirm('Admin-Status wirklich ändern?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="toggle_admin">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button 
                                                        type="submit" 
                                                        class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors" 
                                                        title="Admin-Status ändern"
                                                    >
                                                        <i class="fas fa-crown"></i>
                                                    </button>
                                                </form>
                                                
                                                <!-- Delete User -->
                                                <form method="POST" class="inline" onsubmit="return confirm('Benutzer wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                    <button 
                                                        type="submit" 
                                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors" 
                                                        title="Löschen"
                                                    >
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                           class="px-3 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&order_by=<?php echo urlencode($orderBy); ?>&order_dir=<?php echo urlencode($orderDir); ?>" 
                           class="px-3 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100'; ?> rounded-lg transition-colors">
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

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Benutzer bearbeiten</h3>
                    <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="editUserForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="editUserId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Name</label>
                            <input type="text" name="name" id="editUserName" required 
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">E-Mail</label>
                            <input type="email" name="email" id="editUserEmail" required 
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Bio</label>
                            <textarea name="bio" id="editUserBio" rows="3" 
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_admin" id="editUserAdmin" 
                                   class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            <label for="editUserAdmin" class="ml-2 text-sm text-slate-700">Administrator</label>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" onclick="closeEditModal()" 
                                class="px-4 py-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                            Abbrechen
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('editUserId').value = user.id;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserBio').value = user.bio || '';
    document.getElementById('editUserAdmin').checked = user.is_admin == 1;
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('editUserModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
