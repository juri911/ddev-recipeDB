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

// CSRF-Schutz
csrf_start();

// Dashboard-Statistiken laden
try {
    // Basis-Statistiken
    $stats = [
        'total_users' => (int)db_query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'total_recipes' => (int)db_query('SELECT COUNT(*) FROM recipes')->fetchColumn(),
        'total_comments' => (int)db_query('SELECT COUNT(*) FROM recipe_comments')->fetchColumn(),
        'total_categories' => (int)db_query('SELECT COUNT(*) FROM recipe_categories')->fetchColumn(),
        'total_likes' => (int)db_query('SELECT SUM(likes_count) FROM recipes')->fetchColumn(),
        'total_views' => (int)db_query('SELECT COUNT(*) FROM user_activity_logs WHERE action = "page_visit"')->fetchColumn(),
    ];
    
    // Heute's Aktivität
    $today_stats = [
        'new_users' => (int)db_query('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'new_recipes' => (int)db_query('SELECT COUNT(*) FROM recipes WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'new_comments' => (int)db_query('SELECT COUNT(*) FROM recipe_comments WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
        'page_visits' => (int)db_query('SELECT COUNT(*) FROM user_activity_logs WHERE action = "page_visit" AND DATE(created_at) = CURDATE()')->fetchColumn(),
    ];
    
    // Letzte 7 Tage - Registrierungen
    $registrations_7days = db_query('
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM users 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ')->fetchAll();
    
    // Letzte 7 Tage - Rezepte
    $recipes_7days = db_query('
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM recipes 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ')->fetchAll();
    
    // Letzte 7 Tage - Kommentare
    $comments_7days = db_query('
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM recipe_comments 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ')->fetchAll();
    
    // Letzte 30 Tage - Seitenaufrufe
    $page_views_30days = db_query('
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM user_activity_logs 
        WHERE action = "page_visit" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ')->fetchAll();
    
    // Top Kategorien
    $top_categories = db_query('
        SELECT rc.name, rc.color, rc.icon, COUNT(r.id) as recipe_count
        FROM recipe_categories rc
        LEFT JOIN recipes r ON r.category = rc.name
        GROUP BY rc.name, rc.color, rc.icon
        ORDER BY recipe_count DESC
        LIMIT 5
    ')->fetchAll();
    
    // Top Autoren (Rezepte)
    $top_authors = db_query('
        SELECT u.name, u.avatar_path, COUNT(r.id) as recipe_count, SUM(r.likes_count) as total_likes
        FROM users u
        JOIN recipes r ON r.user_id = u.id
        GROUP BY u.id, u.name, u.avatar_path
        ORDER BY recipe_count DESC, total_likes DESC
        LIMIT 5
    ')->fetchAll();
    
    // Neueste Aktivitäten
    $recent_activities = db_query('
        SELECT ual.*, u.name as user_name, u.avatar_path
        FROM user_activity_logs ual
        LEFT JOIN users u ON ual.user_id = u.id
        WHERE ual.action IN ("login", "registration", "admin_user_update", "admin_recipe_delete", "admin_comment_delete")
        ORDER BY ual.created_at DESC
        LIMIT 10
    ')->fetchAll();
    
    // Schwierigkeitsgrade-Verteilung
    $difficulty_distribution = db_query('
        SELECT difficulty, COUNT(*) as count
        FROM recipes
        GROUP BY difficulty
        ORDER BY FIELD(difficulty, "easy", "medium", "hard")
    ')->fetchAll();
    
    // Browser-Statistiken (User Agents)
    $browser_stats = db_query('
        SELECT 
            CASE 
                WHEN user_agent LIKE "%Chrome%" THEN "Chrome"
                WHEN user_agent LIKE "%Firefox%" THEN "Firefox"
                WHEN user_agent LIKE "%Safari%" AND user_agent NOT LIKE "%Chrome%" THEN "Safari"
                WHEN user_agent LIKE "%Edge%" THEN "Edge"
                WHEN user_agent LIKE "%Opera%" THEN "Opera"
                ELSE "Andere"
            END as browser,
            COUNT(*) as count
        FROM user_activity_logs 
        WHERE user_agent IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY browser
        ORDER BY count DESC
        LIMIT 6
    ')->fetchAll();
    
} catch (Exception $e) {
    $error = "Fehler beim Laden der Dashboard-Daten: " . $e->getMessage();
    // Fallback-Werte
    $stats = ['total_users' => 0, 'total_recipes' => 0, 'total_comments' => 0, 'total_categories' => 0, 'total_likes' => 0, 'total_views' => 0];
    $today_stats = ['new_users' => 0, 'new_recipes' => 0, 'new_comments' => 0, 'page_visits' => 0];
    $registrations_7days = $recipes_7days = $comments_7days = $page_views_30days = [];
    $top_categories = $top_authors = $recent_activities = $difficulty_distribution = $browser_stats = [];
}

// Daten für Charts vorbereiten
function prepare_chart_data($data, $days = 7) {
    $result = [];
    $labels = [];
    
    // Erstelle Array für alle Tage
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = date('d.m', strtotime($date));
        $result[$date] = 0;
    }
    
    // Fülle mit tatsächlichen Daten
    foreach ($data as $row) {
        if (isset($result[$row['date']])) {
            $result[$row['date']] = (int)$row['count'];
        }
    }
    
    return [
        'labels' => $labels,
        'data' => array_values($result)
    ];
}

$chart_registrations = prepare_chart_data($registrations_7days, 7);
$chart_recipes = prepare_chart_data($recipes_7days, 7);
$chart_comments = prepare_chart_data($comments_7days, 7);
$chart_page_views = prepare_chart_data($page_views_30days, 30);

// Set page title for header
$pageTitle = 'Dashboard - Admin - ' . APP_NAME;

include __DIR__ . '/../includes/header.php';
?>

    <div class="min-h-screen bg-slate-50">
        <!-- Header Section -->
        <div class="bg-gradient-to-r from-blue-600 via-purple-600 to-indigo-600 text-white">
            <div class="container mx-auto px-6 py-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold mb-2">Dashboard</h1>
                        <p class="text-blue-100">Willkommen zurück, <?php echo htmlspecialchars($user['name']); ?>! Hier ist deine Übersicht.</p>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-blue-100">Letzter Login</div>
                        <div class="text-lg font-semibold"><?php echo date('d.m.Y H:i'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-6 py-8">
            <?php if (isset($error)): ?>
                <div class="mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-exclamation-triangle text-red-600"></i>
                            <span class="text-red-800 font-medium"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Hauptstatistiken -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-blue-100 rounded-xl">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Benutzer</p>
                            <p class="text-3xl font-bold text-slate-800"><?php echo number_format($stats['total_users']); ?></p>
                            <p class="text-xs text-green-600 font-medium">+<?php echo $today_stats['new_users']; ?> heute</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-green-100 rounded-xl">
                            <i class="fas fa-utensils text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Rezepte</p>
                            <p class="text-3xl font-bold text-slate-800"><?php echo number_format($stats['total_recipes']); ?></p>
                            <p class="text-xs text-green-600 font-medium">+<?php echo $today_stats['new_recipes']; ?> heute</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-purple-100 rounded-xl">
                            <i class="fas fa-comments text-purple-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Kommentare</p>
                            <p class="text-3xl font-bold text-slate-800"><?php echo number_format($stats['total_comments']); ?></p>
                            <p class="text-xs text-green-600 font-medium">+<?php echo $today_stats['new_comments']; ?> heute</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-orange-100 rounded-xl">
                            <i class="fas fa-eye text-orange-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Aufrufe</p>
                            <p class="text-3xl font-bold text-slate-800"><?php echo number_format($stats['total_views']); ?></p>
                            <p class="text-xs text-blue-600 font-medium"><?php echo $today_stats['page_visits']; ?> heute</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200 hover:shadow-xl transition-shadow">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-indigo-100 rounded-xl">
                            <i class="fas fa-tags text-indigo-600 text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-slate-600 text-sm font-medium">Kategorien</p>
                            <p class="text-3xl font-bold text-slate-800"><?php echo number_format($stats['total_categories']); ?></p>
                            <p class="text-xs text-slate-500 font-medium">Aktiv</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Sektion -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Registrierungen Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-slate-800">Registrierungen (7 Tage)</h3>
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-user-plus text-blue-600"></i>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                </div>
                
                <!-- Rezepte Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-slate-800">Neue Rezepte (7 Tage)</h3>
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-utensils text-green-600"></i>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="recipesChart"></canvas>
                    </div>
                </div>
                
                <!-- Kommentare Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-slate-800">Kommentare (7 Tage)</h3>
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-comments text-purple-600"></i>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="commentsChart"></canvas>
                    </div>
                </div>
                
                <!-- Seitenaufrufe Chart -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold text-slate-800">Seitenaufrufe (30 Tage)</h3>
                        <div class="p-2 bg-orange-100 rounded-lg">
                            <i class="fas fa-eye text-orange-600"></i>
                        </div>
                    </div>
                    <div style="height: 200px;">
                        <canvas id="pageViewsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Weitere Statistiken -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                <!-- Top Kategorien -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800 mb-6">Top Kategorien</h3>
                    <div class="space-y-4">
                        <?php foreach ($top_categories as $category): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" 
                                         style="background-color: <?php echo htmlspecialchars($category['color']); ?>">
                                        <i class="fas <?php echo htmlspecialchars($category['icon']); ?> text-white"></i>
                                    </div>
                                    <span class="font-medium text-slate-700"><?php echo htmlspecialchars($category['name']); ?></span>
                                </div>
                                <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded-full text-sm font-medium">
                                    <?php echo $category['recipe_count']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Schwierigkeitsgrade -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800 mb-6">Schwierigkeitsgrade</h3>
                    <div style="height: 250px;">
                        <canvas id="difficultyChart"></canvas>
                    </div>
                </div>
                
                <!-- Browser-Statistiken -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800 mb-6">Browser (7 Tage)</h3>
                    <div style="height: 250px;">
                        <canvas id="browserChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Autoren und Aktivitäten -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Top Autoren -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800 mb-6">Top Autoren</h3>
                    <div class="space-y-4">
                        <?php foreach ($top_authors as $index => $author): ?>
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0">
                                    <span class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </div>
                                <img src="<?php echo htmlspecialchars($author['avatar_path'] ? absolute_url_from_path($author['avatar_path']) : '/images/default_avatar.png'); ?>" 
                                     alt="Avatar" 
                                     class="w-10 h-10 rounded-full object-cover">
                                <div class="flex-1">
                                    <div class="font-medium text-slate-800"><?php echo htmlspecialchars($author['name']); ?></div>
                                    <div class="text-sm text-slate-500">
                                        <?php echo $author['recipe_count']; ?> Rezepte • <?php echo number_format($author['total_likes']); ?> Likes
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Neueste Aktivitäten -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-800 mb-6">Neueste Aktivitäten</h3>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-1">
                                    <?php
                                    $iconClass = 'fas fa-circle';
                                    $colorClass = 'text-slate-400';
                                    
                                    switch ($activity['action']) {
                                        case 'login':
                                            $iconClass = 'fas fa-sign-in-alt';
                                            $colorClass = 'text-green-500';
                                            break;
                                        case 'registration':
                                            $iconClass = 'fas fa-user-plus';
                                            $colorClass = 'text-blue-500';
                                            break;
                                        case 'admin_user_update':
                                            $iconClass = 'fas fa-user-edit';
                                            $colorClass = 'text-orange-500';
                                            break;
                                        case 'admin_recipe_delete':
                                            $iconClass = 'fas fa-trash';
                                            $colorClass = 'text-red-500';
                                            break;
                                        case 'admin_comment_delete':
                                            $iconClass = 'fas fa-comment-slash';
                                            $colorClass = 'text-red-500';
                                            break;
                                    }
                                    ?>
                                    <i class="<?php echo $iconClass . ' ' . $colorClass; ?>"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm text-slate-800">
                                        <strong><?php echo htmlspecialchars($activity['user_name'] ?: $activity['username'] ?: 'System'); ?></strong>
                                        <?php echo htmlspecialchars($activity['description']); ?>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        <?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Chart-Konfiguration
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        };

        // Registrierungen Chart
        new Chart(document.getElementById('registrationsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_registrations['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_registrations['data']); ?>,
                    borderColor: '#3B82F6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Rezepte Chart
        new Chart(document.getElementById('recipesChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_recipes['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_recipes['data']); ?>,
                    backgroundColor: '#10B981',
                    borderColor: '#059669',
                    borderWidth: 1
                }]
            },
            options: chartOptions
        });

        // Kommentare Chart
        new Chart(document.getElementById('commentsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_comments['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_comments['data']); ?>,
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: chartOptions
        });

        // Seitenaufrufe Chart (30 Tage)
        new Chart(document.getElementById('pageViewsChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_page_views['labels']); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_page_views['data']); ?>,
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Schwierigkeitsgrade Pie Chart
        new Chart(document.getElementById('difficultyChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($difficulty_distribution, 'difficulty')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($difficulty_distribution, 'count')); ?>,
                    backgroundColor: ['#10B981', '#F59E0B', '#EF4444'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Browser Pie Chart
        new Chart(document.getElementById('browserChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($browser_stats, 'browser')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($browser_stats, 'count')); ?>,
                    backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#6B7280'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
