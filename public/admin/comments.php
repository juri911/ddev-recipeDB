<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/comments.php';
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
        $commentId = (int)($_POST['comment_id'] ?? 0);
        
        try {
            switch ($action) {
                case 'delete':
                    // Get comment details before deletion
                    $comment = db_query('SELECT c.*, u.name as author_name, r.title as recipe_title FROM recipe_comments c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN recipes r ON c.recipe_id = r.id WHERE c.id = ?', [$commentId])->fetch();
                    if (admin_delete_comment($commentId)) {
                        log_admin_action('comment_delete', "Kommentar von '{$comment['author_name']}' zu Rezept '{$comment['recipe_title']}' gelöscht (ID: {$commentId})");
                        $message = 'Kommentar wurde erfolgreich gelöscht';
                    } else {
                        throw new Exception('Fehler beim Löschen des Kommentars');
                    }
                    break;
                    
                case 'update':
                    $content = trim($_POST['content'] ?? '');
                    
                    if (empty($content)) {
                        throw new Exception('Kommentar-Inhalt ist erforderlich');
                    }
                    
                    if (mb_strlen($content) > 2000) {
                        throw new Exception('Kommentar ist zu lang (max. 2000 Zeichen)');
                    }
                    
                    // Get old comment for comparison
                    $oldComment = db_query('SELECT c.*, u.name as author_name, r.title as recipe_title FROM recipe_comments c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN recipes r ON c.recipe_id = r.id WHERE c.id = ?', [$commentId])->fetch();
                    
                    if (admin_update_comment($commentId, $content)) {
                        $oldLength = mb_strlen($oldComment['content']);
                        $newLength = mb_strlen($content);
                        log_admin_action('comment_update', "Kommentar von '{$oldComment['author_name']}' zu Rezept '{$oldComment['recipe_title']}' bearbeitet (ID: {$commentId}, Länge: {$oldLength} → {$newLength} Zeichen)");
                        $message = 'Kommentar wurde erfolgreich aktualisiert';
                    } else {
                        throw new Exception('Fehler beim Aktualisieren des Kommentars');
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

// Get comments and stats - grouped by user
try {
    $sql = 'SELECT c.*, u.name AS author_name, u.avatar_path AS author_avatar_path, r.title AS recipe_title, r.id AS recipe_id 
            FROM recipe_comments c 
            JOIN users u ON u.id = c.user_id 
            JOIN recipes r ON r.id = c.recipe_id';
    $params = [];
    
    if (!empty($search)) {
        $sql .= ' WHERE c.content LIKE ? OR u.name LIKE ? OR r.title LIKE ?';
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY u.name ASC, c.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $allComments = db_query($sql, $params)->fetchAll();
    
    // Group comments by user
    $commentsByUser = [];
    foreach ($allComments as $comment) {
        $userId = $comment['user_id'];
        if (!isset($commentsByUser[$userId])) {
            $commentsByUser[$userId] = [
                'user' => [
                    'id' => $userId,
                    'name' => $comment['author_name'],
                    'avatar_path' => $comment['author_avatar_path']
                ],
                'comments' => []
            ];
        }
        $commentsByUser[$userId]['comments'][] = $comment;
    }
    
    // Count total
    $countSql = 'SELECT COUNT(*) FROM recipe_comments c JOIN users u ON u.id = c.user_id JOIN recipes r ON r.id = c.recipe_id';
    $countParams = [];
    if (!empty($search)) {
        $countSql .= ' WHERE c.content LIKE ? OR u.name LIKE ? OR r.title LIKE ?';
        $countParams = [$searchTerm, $searchTerm, $searchTerm];
    }
    $totalComments = (int)db_query($countSql, $countParams)->fetchColumn();
    
    $totalPages = max(1, (int)ceil($totalComments / $perPage));
    
    // Simple stats
    $totalCommentsAll = (int)db_query('SELECT COUNT(*) FROM recipe_comments')->fetchColumn();
    $recentComments = (int)db_query('SELECT COUNT(*) FROM recipe_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
    $totalUsers = (int)db_query('SELECT COUNT(DISTINCT user_id) FROM recipe_comments')->fetchColumn();
    
} catch (Exception $e) {
    $error = "Fehler beim Laden der Kommentare: " . $e->getMessage();
    $commentsByUser = [];
    $totalComments = 0;
    $totalPages = 1;
    $totalCommentsAll = 0;
    $recentComments = 0;
    $totalUsers = 0;
}

// Set page title and CSRF token for header
$pageTitle = 'Kommentarverwaltung - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-3 bg-gradient-to-r from-blue-500 to-cyan-600 rounded-xl shadow-lg">
                    <i class="fas fa-comments text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Kommentarverwaltung</h1>
                    <p class="text-slate-600">Verwalte alle Kommentare der Website</p>
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
                        <i class="fas fa-comments text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Gesamt</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($totalCommentsAll); ?></p>
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
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($recentComments); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-orange-100 rounded-lg">
                        <i class="fas fa-users text-orange-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Aktive User</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($totalUsers); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <i class="fas fa-search text-purple-600 text-xl"></i>
                    </div>
                    <div>
                        <p class="text-slate-600 text-sm">Gefiltert</p>
                        <p class="text-2xl font-bold text-slate-800"><?php echo number_format($totalComments); ?></p>
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
                        placeholder="Nach Kommentar-Inhalt, Autor oder Rezept suchen..."
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
                <div class="flex gap-2">
                    <select name="order_by" class="px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="created_at" <?php echo $orderBy === 'created_at' ? 'selected' : ''; ?>>Erstellt</option>
                        <option value="author_name" <?php echo $orderBy === 'author_name' ? 'selected' : ''; ?>>Autor</option>
                        <option value="recipe_title" <?php echo $orderBy === 'recipe_title' ? 'selected' : ''; ?>>Rezept</option>
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

        <!-- Comments by User -->
        <div class="space-y-6 mb-8">
            <?php if (empty($commentsByUser)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-comments text-6xl text-slate-300 mb-4"></i>
                    <h3 class="text-xl font-semibold text-slate-600 mb-2">Keine Kommentare gefunden</h3>
                    <p class="text-slate-500">Versuche andere Suchbegriffe oder Filter.</p>
                </div>
            <?php else: ?>
                <?php foreach ($commentsByUser as $userGroup): ?>
                    <div class="bg-white rounded-xl shadow-lg border border-slate-200 overflow-hidden">
                        <!-- User Header -->
                        <div class="p-4 bg-gradient-to-r from-slate-50 to-slate-100 border-b border-slate-200">
                            <div class="flex items-center gap-3">
                                <img 
                                    src="<?php echo htmlspecialchars($userGroup['user']['avatar_path'] ? absolute_url_from_path($userGroup['user']['avatar_path']) : '/images/default_avatar.png'); ?>" 
                                    alt="Avatar" 
                                    class="w-12 h-12 rounded-full object-cover bg-slate-200 border-2 border-white shadow-sm"
                                >
                                <div class="flex-1">
                                    <h3 class="font-semibold text-slate-800 text-lg">
                                        <?php echo htmlspecialchars($userGroup['user']['name']); ?>
                                    </h3>
                                    <p class="text-sm text-slate-600">
                                        <?php echo count($userGroup['comments']); ?> Kommentar<?php echo count($userGroup['comments']) !== 1 ? 'e' : ''; ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-comments mr-1"></i>
                                        <?php echo count($userGroup['comments']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- User's Comments -->
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($userGroup['comments'] as $comment): ?>
                                <div class="p-4 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start gap-4">
                                        <div class="flex-1">
                                            <!-- Comment Meta -->
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="text-sm text-slate-600">
                                                    <i class="fas fa-utensils mr-1"></i>
                                                    <strong><?php echo htmlspecialchars($comment['recipe_title']); ?></strong>
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    <?php echo date('d.m.Y H:i', strtotime($comment['created_at'])); ?>
                                                </div>
                                            </div>
                                            
                                            <!-- Comment Content -->
                                            <div class="mb-3">
                                                <p class="text-slate-700 whitespace-pre-line leading-relaxed">
                                                    <?php echo htmlspecialchars($comment['content']); ?>
                                                </p>
                                            </div>
                                            
                                            <!-- Meta Info -->
                                            <div class="flex items-center justify-between text-xs text-slate-500 mb-3">
                                                <span>ID: <?php echo $comment['id']; ?></span>
                                                <span>Zeichen: <?php echo mb_strlen($comment['content']); ?></span>
                                            </div>
                                            
                                            <!-- Actions -->
                                            <div class="flex items-center gap-2">
                                                <!-- View Recipe -->
                                                <a 
                                                    href="/recipe_view.php?id=<?php echo $comment['recipe_id']; ?>" 
                                                    target="_blank"
                                                    class="px-3 py-1.5 text-xs text-blue-600 border border-blue-600 rounded-lg hover:bg-blue-50 transition-colors"
                                                >
                                                    <i class="fas fa-eye mr-1"></i>Rezept
                                                </a>
                                                
                                                <!-- Edit Comment -->
                                                <button 
                                                    onclick="editComment(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars(addslashes($comment['content'])); ?>', '<?php echo htmlspecialchars(addslashes($comment['recipe_title'])); ?>')"
                                                    class="px-3 py-1.5 text-xs text-green-600 border border-green-600 rounded-lg hover:bg-green-50 transition-colors"
                                                >
                                                    <i class="fas fa-edit mr-1"></i>Bearbeiten
                                                </button>
                                                
                                                <!-- Delete Comment -->
                                                <form method="POST" class="inline" onsubmit="return confirm('Kommentar wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                    <button 
                                                        type="submit" 
                                                        class="px-3 py-1.5 text-xs text-red-600 border border-red-600 rounded-lg hover:bg-red-50 transition-colors"
                                                    >
                                                        <i class="fas fa-trash mr-1"></i>Löschen
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

<!-- Edit Comment Modal -->
<div id="editCommentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Kommentar bearbeiten</h3>
                    <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form id="editCommentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="comment_id" id="editCommentId">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Kommentar</label>
                            <textarea name="content" id="editCommentContent" rows="6" required 
                                      maxlength="2000"
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                            <div class="flex justify-between text-xs text-slate-500 mt-1">
                                <span>Maximal 2000 Zeichen</span>
                                <span id="comment-counter">0/2000</span>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 p-3 rounded-lg">
                            <p class="text-sm text-slate-600 mb-1">Rezept:</p>
                            <p class="font-medium text-slate-800" id="editCommentRecipe"></p>
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
function editComment(id, content, recipeTitle) {
    console.log('Edit comment:', id, content, recipeTitle);
    document.getElementById('editCommentId').value = id;
    document.getElementById('editCommentContent').value = content;
    document.getElementById('editCommentRecipe').textContent = recipeTitle;
    updateCharCounter();
    document.getElementById('editCommentModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editCommentModal').classList.add('hidden');
}

function updateCharCounter() {
    const textarea = document.getElementById('editCommentContent');
    const counter = document.getElementById('comment-counter');
    const length = textarea.value.length;
    counter.textContent = `${length}/2000`;
    
    if (length > 1800) {
        counter.classList.add('text-red-500');
        counter.classList.remove('text-slate-500');
    } else {
        counter.classList.remove('text-red-500');
        counter.classList.add('text-slate-500');
    }
}

// Add event listener for character counter
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('editCommentContent');
    if (textarea) {
        textarea.addEventListener('input', updateCharCounter);
    }
});

// Close modal when clicking outside
document.getElementById('editCommentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
