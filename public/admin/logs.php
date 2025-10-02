<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/logger.php';
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

// Pagination und Filter
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$search = trim($_GET['search'] ?? '');
$actionFilter = $_GET['action_filter'] ?? '';
$userFilter = $_GET['user_filter'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$offset = ($page - 1) * $perPage;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'clean_logs':
                    $days = (int)($_POST['days'] ?? 90);
                    $deleted = clean_old_logs($days);
                    if ($deleted !== false) {
                        $message = "Erfolgreich {$deleted} alte Log-Einträge gelöscht (älter als {$days} Tage)";
                        log_admin_action('clean_logs', "Logs bereinigt: {$deleted} Einträge gelöscht");
                    } else {
                        throw new Exception('Fehler beim Bereinigen der Logs');
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

// Get logs and stats
try {
    $logs = get_user_activity_logs($perPage, $offset, $search, $actionFilter, $userFilter, $dateFrom, $dateTo);
    $totalLogs = count_user_activity_logs($search, $actionFilter, $userFilter, $dateFrom, $dateTo);
    $totalPages = max(1, (int)ceil($totalLogs / $perPage));
    $activityStats = get_activity_stats();
    $availableActions = get_available_actions();
    
} catch (Exception $e) {
    $error = "Fehler beim Laden der Logs: " . $e->getMessage();
    $logs = [];
    $totalLogs = 0;
    $totalPages = 1;
    $activityStats = ['total_logs' => 0, 'today_logs' => 0, 'unique_users_today' => 0, 'most_active_action' => ['action' => 'Fehler', 'count' => 0], 'recent_logins' => 0, 'top_ips' => []];
    $availableActions = [];
}

// Set page title and CSRF token for header
$pageTitle = 'System-Logs - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

include __DIR__ . '/../includes/header.php';
?>

    <div class="min-h-screen bg-slate-50">
        <!-- Header Section -->
        <div class="bg-white border-b border-slate-200 shadow-sm">
            <div class="container mx-auto px-6 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800 mb-2">System-Logs</h1>
                        <p class="text-slate-600">Überwache Benutzeraktivitäten und Systemereignisse</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="openModal('cleanLogsModal')" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-medium rounded-lg shadow-lg hover:shadow-xl transition-all duration-200">
                            <i class="fas fa-broom"></i>
                            <span>Logs bereinigen</span>
                        </button>
                        <button onclick="window.location.reload()" 
                                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white font-medium rounded-lg shadow-lg hover:shadow-xl transition-all duration-200">
                            <i class="fas fa-sync-alt"></i>
                            <span>Aktualisieren</span>
                        </button>
                    </div>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-100 rounded-lg">
                            <i class="fas fa-list text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Gesamt Logs</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($activityStats['total_logs']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-green-100 rounded-lg">
                            <i class="fas fa-calendar-day text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Heute</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($activityStats['today_logs']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-purple-100 rounded-lg">
                            <i class="fas fa-users text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Aktive User</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($activityStats['unique_users_today']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-orange-100 rounded-lg">
                            <i class="fas fa-sign-in-alt text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Logins (24h)</p>
                            <p class="text-2xl font-bold text-slate-800"><?php echo number_format($activityStats['recent_logins']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-indigo-100 rounded-lg">
                            <i class="fas fa-chart-bar text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm">Top Aktion</p>
                            <p class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($activityStats['most_active_action']['action']); ?></p>
                            <p class="text-xs text-slate-500"><?php echo $activityStats['most_active_action']['count']; ?>x heute</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 p-6 mb-8">
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Suche</label>
                            <input type="text" 
                                   name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Benutzer, IP, Beschreibung..." 
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Aktion</label>
                            <select name="action_filter" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Alle Aktionen</option>
                                <?php foreach ($availableActions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Benutzer</label>
                            <input type="text" 
                                   name="user_filter" 
                                   value="<?php echo htmlspecialchars($userFilter); ?>" 
                                   placeholder="User ID oder Name" 
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Von Datum</label>
                            <input type="date" 
                                   name="date_from" 
                                   value="<?php echo htmlspecialchars($dateFrom); ?>" 
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Bis Datum</label>
                            <input type="date" 
                                   name="date_to" 
                                   value="<?php echo htmlspecialchars($dateTo); ?>" 
                                   class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium">
                                <i class="fas fa-search mr-2"></i>Filtern
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Logs Table -->
            <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden mb-8">
                <?php if (empty($logs)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-file-alt text-6xl text-slate-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-slate-600 mb-2">Keine Logs gefunden</h3>
                        <p class="text-slate-500">Versuche andere Filter oder Suchbegriffe.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Zeit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Benutzer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Aktion</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Beschreibung</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">IP-Adresse</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-200">
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                            <div class="flex flex-col">
                                                <span class="font-medium"><?php echo date('d.m.Y', strtotime($log['created_at'])); ?></span>
                                                <span class="text-slate-500"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($log['user_id']): ?>
                                                <div class="flex items-center gap-3">
                                                    <img src="<?php echo htmlspecialchars($log['avatar_path'] ? absolute_url_from_path($log['avatar_path']) : '/images/default_avatar.png'); ?>" 
                                                         alt="Avatar" 
                                                         class="w-8 h-8 rounded-full object-cover bg-slate-200">
                                                    <div>
                                                        <div class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($log['user_name'] ?: $log['username']); ?></div>
                                                        <div class="text-sm text-slate-500">ID: <?php echo $log['user_id']; ?></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user-secret text-slate-400 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-slate-600"><?php echo htmlspecialchars($log['username'] ?: 'Anonym'); ?></div>
                                                        <div class="text-sm text-slate-500">Gast</div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $actionColors = [
                                                'login' => 'bg-green-100 text-green-800',
                                'logout' => 'bg-red-100 text-red-800',
                                'login_failed' => 'bg-red-100 text-red-800',
                                'registration' => 'bg-blue-100 text-blue-800',
                                'page_visit' => 'bg-slate-100 text-slate-800',
                                'admin_' => 'bg-purple-100 text-purple-800'
                            ];
                            
                            $colorClass = 'bg-slate-100 text-slate-800';
                            foreach ($actionColors as $prefix => $color) {
                                if (str_starts_with($log['action'], $prefix)) {
                                    $colorClass = $color;
                                    break;
                                }
                            }
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-900">
                            <div class="max-w-xs truncate" title="<?php echo htmlspecialchars($log['description']); ?>">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-globe text-slate-400"></i>
                                <span class="font-mono"><?php echo htmlspecialchars($log['ip_address'] ?: 'unknown'); ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <button onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES, 'UTF-8'); ?>)"
                                    class="text-blue-600 hover:text-blue-800 transition-colors">
                                <i class="fas fa-info-circle mr-1"></i>Details
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="flex justify-center">
        <nav class="flex items-center gap-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($actionFilter); ?>&user_filter=<?php echo urlencode($userFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                   class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                    <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($actionFilter); ?>&user_filter=<?php echo urlencode($userFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                   class="px-4 py-2 <?php echo $i === $page ? 'bg-blue-600 text-white' : 'text-slate-600 border border-slate-300 hover:bg-slate-50'; ?> rounded-lg transition-colors">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&action_filter=<?php echo urlencode($actionFilter); ?>&user_filter=<?php echo urlencode($userFilter); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>" 
                   class="px-4 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>
</div>
</div>

<!-- Modal: Clean Logs -->
<div id="cleanLogsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog">
<div class="bg-white rounded-xl shadow-2xl w-full max-w-md transform transition-all">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="action" value="clean_logs">
        
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-slate-200">
            <h3 class="text-xl font-semibold text-slate-800">Logs bereinigen</h3>
            <button type="button" onclick="closeModal('cleanLogsModal')"
                    class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-broom text-amber-600 text-xl"></i>
                </div>
                <div>
                    <h4 class="font-medium text-slate-800">Alte Logs löschen</h4>
                    <p class="text-sm text-slate-600">Entferne Log-Einträge älter als die angegebenen Tage.</p>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-2">Tage (Logs älter als X Tage löschen)</label>
                <input type="number" name="days" value="90" min="1" max="365" 
                       class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors">
                <p class="text-xs text-slate-500 mt-1">Standard: 90 Tage. Empfohlen: 30-180 Tage</p>
            </div>
            
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5"></i>
                    <div class="text-sm text-amber-800">
                        <p class="font-medium mb-1">Wichtiger Hinweis:</p>
                        <p>Diese Aktion kann nicht rückgängig gemacht werden. Gelöschte Logs sind permanent verloren.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex items-center justify-end gap-3 p-6 border-t border-slate-200">
            <button type="button" onclick="closeModal('cleanLogsModal')"
                    class="px-6 py-2 text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">
                Abbrechen
            </button>
            <button type="submit"
                    class="px-6 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition-colors font-medium">
                <i class="fas fa-broom mr-2"></i>Logs bereinigen
            </button>
        </div>
    </form>
</div>
</div>

<!-- Modal: Log Details -->
<div id="logDetailsModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4" role="dialog">
<div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl transform transition-all">
    <!-- Modal Header -->
    <div class="flex items-center justify-between p-6 border-b border-slate-200">
        <h3 class="text-xl font-semibold text-slate-800">Log-Details</h3>
        <button type="button" onclick="closeModal('logDetailsModal')"
                class="text-slate-400 hover:text-slate-600 transition-colors">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    
    <!-- Modal Body -->
    <div class="p-6" id="logDetailsContent">
        <!-- Content will be populated by JavaScript -->
    </div>
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
        }
    };

    window.closeModal = function(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };

    window.showLogDetails = function(log) {
        const content = document.getElementById('logDetailsContent');
        if (content) {
            content.innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">ID</label>
                            <div class="text-sm text-slate-900">${log.id}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Zeitstempel</label>
                            <div class="text-sm text-slate-900">${new Date(log.created_at).toLocaleString('de-DE')}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Benutzer ID</label>
                            <div class="text-sm text-slate-900">${log.user_id || 'Nicht angemeldet'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Benutzername</label>
                            <div class="text-sm text-slate-900">${log.username || 'Anonym'}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Aktion</label>
                            <div class="text-sm text-slate-900">${log.action}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">IP-Adresse</label>
                            <div class="text-sm text-slate-900 font-mono">${log.ip_address || 'Unbekannt'}</div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Beschreibung</label>
                        <div class="text-sm text-slate-900 bg-slate-50 p-3 rounded-lg">${log.description || 'Keine Beschreibung'}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">User Agent</label>
                        <div class="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg break-all">${log.user_agent || 'Nicht verfügbar'}</div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Session ID</label>
                        <div class="text-xs text-slate-600 font-mono bg-slate-50 p-3 rounded-lg break-all">${log.session_id || 'Nicht verfügbar'}</div>
                    </div>
                </div>
            `;
        }
        openModal('logDetailsModal');
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

    // Auto-refresh every 30 seconds
    setInterval(function() {
        const refreshButton = document.querySelector('button[onclick="window.location.reload()"]');
        if (refreshButton && !document.querySelector('.fixed:not(.hidden)')) {
            // Only refresh if no modal is open
            window.location.reload();
        }
    }, 30000);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
