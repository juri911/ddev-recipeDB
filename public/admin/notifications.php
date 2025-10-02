<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/notifications.php';
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
$error = '';

// Check for session messages (from redirects)
if (isset($_SESSION['notification_message'])) {
    $message = $_SESSION['notification_message'];
    unset($_SESSION['notification_message']);
}

if (isset($_SESSION['notification_error'])) {
    $error = $_SESSION['notification_error'];
    unset($_SESSION['notification_error']);
}

// Check if we should update the badge (after marking own notifications as read)
$updateBadge = isset($_SESSION['update_badge']);
if ($updateBadge) {
    unset($_SESSION['update_badge']);
}

// CSRF-Schutz
csrf_start();

// Pagination und Filter
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$search = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type_filter'] ?? '';
$statusFilter = $_GET['status_filter'] ?? '';
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
                case 'broadcast_notification':
                    $broadcastMessage = trim($_POST['broadcast_message'] ?? '');
                    $broadcastType = $_POST['broadcast_type'] ?? 'system';
                    
                    if (empty($broadcastMessage)) {
                        throw new Exception('Nachricht darf nicht leer sein');
                    }
                    
                    // Get all users
                    $users = db_query('SELECT id FROM users')->fetchAll();
                    $count = 0;
                    
                    foreach ($users as $targetUser) {
                        add_notification((int)$targetUser['id'], $broadcastType, null, $broadcastMessage);
                        $count++;
                    }
                    
                    log_admin_action('broadcast_notification', "Broadcast-Nachricht an {$count} Benutzer gesendet: {$broadcastMessage}");
                    
                    // Redirect to prevent double submission
                    $_SESSION['notification_message'] = "Nachricht erfolgreich an {$count} Benutzer gesendet";
                    header('Location: /admin/notifications.php');
                    exit;
                    
                case 'mark_all_read':
                    $affected = db_query('UPDATE notifications SET is_read = 1 WHERE is_read = 0')->rowCount();
                    log_admin_action('mark_all_notifications_read', "Alle Benachrichtigungen als gelesen markiert ({$affected} Nachrichten)");
                    
                    $_SESSION['notification_message'] = "{$affected} Benachrichtigungen (systemweit) als gelesen markiert";
                    header('Location: /admin/notifications.php');
                    exit;
                    
                case 'mark_my_read':
                    $affected = db_query('UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND user_id = ?', [$user['id']])->rowCount();
                    log_admin_action('mark_my_notifications_read', "Eigene Benachrichtigungen als gelesen markiert ({$affected} Nachrichten)");
                    
                    $_SESSION['notification_message'] = "{$affected} Ihrer Benachrichtigungen als gelesen markiert";
                    $_SESSION['update_badge'] = true; // Flag to update badge
                    header('Location: /admin/notifications.php');
                    exit;
                    
                case 'delete_old':
                    $days = (int)($_POST['days'] ?? 30);
                    $affected = db_query('DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days])->rowCount();
                    log_admin_action('delete_old_notifications', "Alte Benachrichtigungen gelöscht ({$affected} Nachrichten älter als {$days} Tage)");
                    
                    $_SESSION['notification_message'] = "{$affected} alte Benachrichtigungen gelöscht";
                    header('Location: /admin/notifications.php');
                    exit;
                    
                case 'delete_notification':
                    $notificationId = (int)($_POST['notification_id'] ?? 0);
                    $affected = db_query('DELETE FROM notifications WHERE id = ?', [$notificationId])->rowCount();
                    if ($affected > 0) {
                        log_admin_action('delete_notification', "Benachrichtigung gelöscht (ID: {$notificationId})");
                        $_SESSION['notification_message'] = 'Benachrichtigung gelöscht';
                    } else {
                        $_SESSION['notification_error'] = 'Benachrichtigung nicht gefunden';
                    }
                    header('Location: /admin/notifications.php');
                    exit;
                    
                case 'bulk_action':
                    $bulkAction = $_POST['bulk_action'] ?? '';
                    $selectedIds = $_POST['selected_notifications'] ?? [];
                    
                    if (empty($selectedIds)) {
                        throw new Exception('Keine Benachrichtigungen ausgewählt');
                    }
                    
                    $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';
                    
                    switch ($bulkAction) {
                        case 'mark_read':
                            $affected = db_query("UPDATE notifications SET is_read = 1 WHERE id IN ({$placeholders})", $selectedIds)->rowCount();
                            $_SESSION['notification_message'] = "{$affected} Benachrichtigungen als gelesen markiert";
                            break;
                            
                        case 'mark_unread':
                            $affected = db_query("UPDATE notifications SET is_read = 0 WHERE id IN ({$placeholders})", $selectedIds)->rowCount();
                            $_SESSION['notification_message'] = "{$affected} Benachrichtigungen als ungelesen markiert";
                            break;
                            
                        case 'delete':
                            $affected = db_query("DELETE FROM notifications WHERE id IN ({$placeholders})", $selectedIds)->rowCount();
                            $_SESSION['notification_message'] = "{$affected} Benachrichtigungen gelöscht";
                            break;
                            
                        default:
                            throw new Exception('Unbekannte Bulk-Aktion');
                    }
                    
                    log_admin_action('bulk_notification_action', "Bulk-Aktion '{$bulkAction}' auf {$affected} Benachrichtigungen angewendet");
                    header('Location: /admin/notifications.php');
                    exit;
                    
                default:
                    throw new Exception('Unbekannte Aktion');
            }
        } catch (Exception $e) {
            $_SESSION['notification_error'] = $e->getMessage();
            header('Location: /admin/notifications.php');
            exit;
        }
    }
}

// Get notifications with filters
try {
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        $whereConditions[] = 'n.message LIKE ?';
        $params[] = "%{$search}%";
    }
    
    if (!empty($typeFilter)) {
        $whereConditions[] = 'n.type = ?';
        $params[] = $typeFilter;
    }
    
    if (!empty($statusFilter)) {
        if ($statusFilter === 'read') {
            $whereConditions[] = 'n.is_read = 1';
        } elseif ($statusFilter === 'unread') {
            $whereConditions[] = 'n.is_read = 0';
        }
    }
    
    if (!empty($userFilter)) {
        $whereConditions[] = 'u.name LIKE ?';
        $params[] = "%{$userFilter}%";
    }
    
    if (!empty($dateFrom)) {
        $whereConditions[] = 'DATE(n.created_at) >= ?';
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $whereConditions[] = 'DATE(n.created_at) <= ?';
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get notifications
    $notifications = db_query("
        SELECT n.*, u.name as user_name, u.email as user_email
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        {$whereClause}
        ORDER BY n.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $params)->fetchAll();
    
    // Get total count
    $totalCount = (int)db_query("
        SELECT COUNT(*)
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        {$whereClause}
    ", $params)->fetchColumn();
    
    $totalPages = ceil($totalCount / $perPage);
    
    // Get statistics
    $stats = [
        'total' => (int)db_query('SELECT COUNT(*) FROM notifications')->fetchColumn(),
        'unread' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn(),
        'today' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'this_week' => (int)db_query('SELECT COUNT(*) FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
    ];
    
    // Get notification types
    $notificationTypes = db_query('SELECT DISTINCT type FROM notifications ORDER BY type')->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    error_log('Notification loading error: ' . $e->getMessage());
    $error = 'Fehler beim Laden der Benachrichtigungen: ' . $e->getMessage();
    $notifications = [];
    $totalCount = 0;
    $totalPages = 0;
    $stats = ['total' => 0, 'unread' => 0, 'today' => 0, 'this_week' => 0];
    $notificationTypes = [];
}

$csrfToken = csrf_token();
?>

<?php
$pageTitle = 'Benachrichtigungen verwalten - Admin - ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-50">
    <div class="container mx-auto px-6 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-bell text-blue-600 mr-3"></i>
                Benachrichtigungen verwalten
            </h1>
            <p class="text-gray-600">Übersicht und Verwaltung aller Systembenachrichtigungen</p>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Gesamt</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell text-blue-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Ungelesen</p>
                        <p class="text-2xl font-bold text-orange-600"><?= number_format($stats['unread']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bell-slash text-orange-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Heute</p>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($stats['today']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-day text-green-600"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Diese Woche</p>
                        <p class="text-2xl font-bold text-purple-600"><?= number_format($stats['this_week']) ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-week text-purple-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-tools text-gray-600 mr-2"></i>
                Schnellaktionen
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Broadcast Message -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">
                        <i class="fas fa-broadcast-tower text-blue-600 mr-2"></i>
                        Broadcast-Nachricht
                    </h3>
                    <form method="POST" class="space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="broadcast_notification">
                        <textarea name="broadcast_message" placeholder="Nachricht an alle Benutzer..." 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" rows="2" required></textarea>
                        <select name="broadcast_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="system">System</option>
                            <option value="announcement">Ankündigung</option>
                            <option value="maintenance">Wartung</option>
                        </select>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-paper-plane mr-1"></i>
                            Senden
                        </button>
                    </form>
                </div>

                <!-- Mark All Read -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">
                        <i class="fas fa-check-double text-green-600 mr-2"></i>
                        Als gelesen markieren
                    </h3>
                    <div class="space-y-2">
                        <form method="POST" onsubmit="return markAllNotificationsRead(event)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-check-double mr-1"></i>
                                Alle (System)
                            </button>
                        </form>
                        
                        <form method="POST" onsubmit="return markMyNotificationsRead(event)">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="mark_my_read">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-check mr-1"></i>
                                Meine eigenen
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Delete Old -->
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-medium text-gray-900 mb-2">
                        <i class="fas fa-trash text-red-600 mr-2"></i>
                        Alte Benachrichtigungen löschen
                    </h3>
                    <form method="POST" onsubmit="return confirm('Alte Benachrichtigungen wirklich löschen?')" class="space-y-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_old">
                        <select name="days" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="30">Älter als 30 Tage</option>
                            <option value="60">Älter als 60 Tage</option>
                            <option value="90">Älter als 90 Tage</option>
                        </select>
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-trash mr-1"></i>
                            Löschen
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                <i class="fas fa-filter text-gray-600 mr-2"></i>
                Filter
            </h2>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Suche</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Nachricht durchsuchen..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ</label>
                    <select name="type_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Alle Typen</option>
                        <?php foreach ($notificationTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($type)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status_filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">Alle Status</option>
                        <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Ungelesen</option>
                        <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Gelesen</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Benutzer</label>
                    <input type="text" name="user_filter" value="<?= htmlspecialchars($userFilter) ?>" 
                           placeholder="Benutzername..." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Von Datum</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bis Datum</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                
                <div class="md:col-span-2 lg:col-span-3 xl:col-span-6 flex gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        <i class="fas fa-search mr-1"></i>
                        Filtern
                    </button>
                    <a href="/admin/notifications.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        <i class="fas fa-times mr-1"></i>
                        Zurücksetzen
                    </a>
                </div>
            </form>
        </div>

        <!-- Notifications List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        Benachrichtigungen (<?= number_format($totalCount) ?>)
                    </h2>
                    
                    <!-- Bulk Actions -->
                    <div class="flex items-center gap-2">
                        <select id="bulk-action" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Bulk-Aktion wählen...</option>
                            <option value="mark_read">Als gelesen markieren</option>
                            <option value="mark_unread">Als ungelesen markieren</option>
                            <option value="delete">Löschen</option>
                        </select>
                        <button id="apply-bulk" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-check mr-1"></i>
                            Anwenden
                        </button>
                    </div>
                </div>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="p-12 text-center">
                    <i class="fas fa-bell-slash text-4xl text-gray-300 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Benachrichtigungen gefunden</h3>
                    <p class="text-gray-500">Es wurden keine Benachrichtigungen gefunden, die Ihren Filterkriterien entsprechen.</p>
                </div>
            <?php else: ?>
                <form id="bulk-form" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_action">
                    <input type="hidden" name="bulk_action" id="bulk-action-input">
                    
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            // Define notification styling based on type
                            $notificationStyles = [
                                'system' => [
                                    'border' => 'border-l-4 border-blue-500',
                                    'bg' => 'bg-blue-50',
                                    'icon' => 'fas fa-cog',
                                    'iconColor' => 'text-blue-600',
                                    'iconBg' => 'bg-blue-100',
                                    'badge' => 'bg-blue-100 text-blue-800'
                                ],
                                'announcement' => [
                                    'border' => 'border-l-4 border-green-500',
                                    'bg' => 'bg-green-50',
                                    'icon' => 'fas fa-bullhorn',
                                    'iconColor' => 'text-green-600',
                                    'iconBg' => 'bg-green-100',
                                    'badge' => 'bg-green-100 text-green-800'
                                ],
                                'maintenance' => [
                                    'border' => 'border-l-4 border-yellow-500',
                                    'bg' => 'bg-yellow-50',
                                    'icon' => 'fas fa-tools',
                                    'iconColor' => 'text-yellow-600',
                                    'iconBg' => 'bg-yellow-100',
                                    'badge' => 'bg-yellow-100 text-yellow-800'
                                ],
                                'new_recipe' => [
                                    'border' => 'border-l-4 border-purple-500',
                                    'bg' => 'bg-purple-50',
                                    'icon' => 'fas fa-utensils',
                                    'iconColor' => 'text-purple-600',
                                    'iconBg' => 'bg-purple-100',
                                    'badge' => 'bg-purple-100 text-purple-800'
                                ]
                            ];
                            
                            $style = $notificationStyles[$notification['type']] ?? [
                                'border' => 'border-l-4 border-gray-500',
                                'bg' => 'bg-gray-50',
                                'icon' => 'fas fa-bell',
                                'iconColor' => 'text-gray-600',
                                'iconBg' => 'bg-gray-100',
                                'badge' => 'bg-gray-100 text-gray-800'
                            ];
                            ?>
                            <div class="p-6 hover:bg-gray-50 transition-colors <?= $style['border'] ?> <?= $style['bg'] ?> relative">
                                <!-- Background Icon -->
                                <div class="absolute top-4 right-4 opacity-10">
                                    <i class="<?= $style['icon'] ?> text-6xl <?= $style['iconColor'] ?>"></i>
                                </div>
                                
                                <div class="flex items-start gap-4 relative z-10">
                                    <input type="checkbox" name="selected_notifications[]" value="<?= $notification['id'] ?>" 
                                           class="mt-1 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                    
                                    <!-- Type Icon -->
                                    <div class="flex-shrink-0 w-10 h-10 <?= $style['iconBg'] ?> rounded-full flex items-center justify-center mt-1">
                                        <i class="<?= $style['icon'] ?> <?= $style['iconColor'] ?>"></i>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $style['badge'] ?>">
                                                        <i class="<?= $style['icon'] ?> mr-1"></i>
                                                        <?= htmlspecialchars(ucfirst($notification['type'])) ?>
                                                    </span>
                                                    
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                            <i class="fas fa-circle mr-1 text-orange-500" style="font-size: 6px;"></i>
                                                            Ungelesen
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <p class="text-gray-900 <?= !$notification['is_read'] ? 'font-medium' : '' ?> mb-2">
                                                    <?= htmlspecialchars($notification['message']) ?>
                                                </p>
                                                
                                                <div class="flex items-center gap-4 text-sm text-gray-500">
                                                    <span class="flex items-center">
                                                        <i class="fas fa-user mr-1"></i>
                                                        <?= htmlspecialchars($notification['user_name'] ?? 'System') ?>
                                                    </span>
                                                    <span class="flex items-center">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <?= date('d.m.Y H:i', strtotime($notification['created_at'])) ?>
                                                    </span>
                                                    <?php if ($notification['entity_id']): ?>
                                                        <span class="flex items-center">
                                                            <i class="fas fa-link mr-1"></i>
                                                            ID: <?= $notification['entity_id'] ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2 ml-4">
                                                <form method="POST" class="inline" onsubmit="return confirm('Benachrichtigung wirklich löschen?')">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_notification">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Löschen">
                                                        <i class="fas fa-trash text-sm"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php endif; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Zeige <?= ($offset + 1) ?> bis <?= min($offset + $perPage, $totalCount) ?> von <?= $totalCount ?> Einträgen
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="px-3 py-2 border rounded-lg text-sm <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // ===== NOTIFICATION BADGE MANAGEMENT =====
    
    /**
     * Use the global badge management system from header.php
     * This ensures ALL badges across the website are updated synchronously
     */
    function updateNotificationBadges(count) {
        if (window.NotificationBadgeManager) {
            console.log('[Admin] Using global badge manager');
            window.NotificationBadgeManager.updateAllBadges(count);
        } else {
            console.warn('[Admin] Global badge manager not available, using fallback');
            // Fallback for compatibility
            const badges = document.querySelectorAll('.notification-badge');
            badges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.opacity = '0';
                    setTimeout(() => badge.remove(), 300);
                }
            });
        }
    }
    
    /**
     * Fetch current unread notification count from server
     */
    function fetchNotificationCount() {
        if (window.NotificationBadgeManager) {
            console.log('[Admin] Using global fetch method');
            return window.NotificationBadgeManager.fetchAndUpdate();
        } else {
            console.warn('[Admin] Global badge manager not available, using fallback');
            // Fallback method
            return fetch('/api/get_unread_count.php')
                .then(response => response.json())
                .then(data => {
                    updateNotificationBadges(data.count);
                    return data.count;
                })
                .catch(error => {
                    console.error('Error fetching notification count:', error);
                    return 0;
                });
        }
    }
    
    /**
     * AJAX function to mark own notifications as read
     */
    function markMyNotificationsRead(event) {
        event.preventDefault();
        
        if (!confirm('Ihre eigenen Benachrichtigungen als gelesen markieren?')) {
            return false;
        }
        
        const form = event.target;
        const formData = new FormData(form);
        
        fetch('/admin/notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(() => {
            // Update badges immediately
            fetchNotificationCount();
            
            // Show success message
            showMessage('Ihre Benachrichtigungen wurden als gelesen markiert', 'success');
            
            // Reload page to show updated list
            setTimeout(() => location.reload(), 1000);
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
            showMessage('Fehler beim Markieren der Benachrichtigungen', 'error');
        });
        
        return false;
    }
    
    /**
     * AJAX function to mark ALL notifications as read (system-wide)
     */
    function markAllNotificationsRead(event) {
        event.preventDefault();
        
        if (!confirm('Alle Benachrichtigungen aller Benutzer als gelesen markieren?')) {
            return false;
        }
        
        const form = event.target;
        const formData = new FormData(form);
        
        fetch('/admin/notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(() => {
            // Update badges immediately (will remove them since all are read)
            fetchNotificationCount();
            
            // Show success message
            showMessage('Alle Benachrichtigungen wurden systemweit als gelesen markiert', 'success');
            
            // Reload page to show updated list
            setTimeout(() => location.reload(), 1000);
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
            showMessage('Fehler beim Markieren der Benachrichtigungen', 'error');
        });
        
        return false;
    }
    
    /**
     * Simple message display function
     */
    function showMessage(text, type) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `mb-6 px-4 py-3 rounded-lg ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`;
        messageDiv.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>${text}`;
        
        const container = document.querySelector('.container');
        const firstChild = container.firstElementChild;
        container.insertBefore(messageDiv, firstChild.nextSibling);
        
        // Remove message after 3 seconds
        setTimeout(() => messageDiv.remove(), 3000);
    }
    
    // ===== BULK ACTIONS & UI MANAGEMENT =====
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initial badge update
        fetchNotificationCount();
        
        // Bulk action functionality
        const bulkActionSelect = document.getElementById('bulk-action');
        const applyBulkBtn = document.getElementById('apply-bulk');
        const bulkForm = document.getElementById('bulk-form');
        const bulkActionInput = document.getElementById('bulk-action-input');

        if (applyBulkBtn) {
            applyBulkBtn.addEventListener('click', function() {
                const selectedAction = bulkActionSelect.value;
                const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]:checked');
                
                if (!selectedAction) {
                    alert('Bitte wählen Sie eine Aktion aus.');
                    return;
                }
                
                if (checkboxes.length === 0) {
                    alert('Bitte wählen Sie mindestens eine Benachrichtigung aus.');
                    return;
                }
                
                const actionText = {
                    'mark_read': 'als gelesen markieren',
                    'mark_unread': 'als ungelesen markieren',
                    'delete': 'löschen'
                };
                
                if (confirm(`${checkboxes.length} Benachrichtigung(en) ${actionText[selectedAction]}?`)) {
                    bulkActionInput.value = selectedAction;
                    bulkForm.submit();
                }
            });
        }

        // Select all checkbox functionality
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'text-blue-600 hover:text-blue-800 text-sm';
        selectAllBtn.innerHTML = '<i class="fas fa-check-square mr-1"></i>Alle auswählen';
        
        const bulkActionsDiv = document.querySelector('.flex.items-center.gap-2');
        if (bulkActionsDiv) {
            bulkActionsDiv.insertBefore(selectAllBtn, bulkActionsDiv.firstChild);
        }
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => cb.checked = !allChecked);
                selectAllBtn.innerHTML = allChecked 
                    ? '<i class="fas fa-check-square mr-1"></i>Alle auswählen'
                    : '<i class="fas fa-square mr-1"></i>Alle abwählen';
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
