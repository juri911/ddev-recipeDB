<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/settings.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            if ($action === 'update_seo') {
                // SEO-Einstellungen aktualisieren
                $seoData = [
                    'description' => trim($_POST['seo_description'] ?? ''),
                    'keywords' => trim($_POST['seo_keywords'] ?? ''),
                    'author' => trim($_POST['seo_author'] ?? '')
                ];
                
                // Validierung
                if (strlen($seoData['description']) > 500) {
                    throw new Exception('SEO-Beschreibung darf maximal 500 Zeichen lang sein');
                }
                
                if (strlen($seoData['keywords']) > 300) {
                    throw new Exception('SEO-Keywords dürfen maximal 300 Zeichen lang sein');
                }
                
                if (strlen($seoData['author']) > 100) {
                    throw new Exception('Autor darf maximal 100 Zeichen lang sein');
                }
                
                // Get old settings for comparison
                $oldSettings = get_seo_settings();
                
                if (update_seo_settings($seoData)) {
                    $changes = [];
                    if ($oldSettings['description'] !== $seoData['description']) $changes[] = "Beschreibung geändert";
                    if ($oldSettings['keywords'] !== $seoData['keywords']) $changes[] = "Keywords geändert";
                    if ($oldSettings['author'] !== $seoData['author']) $changes[] = "Autor: '{$oldSettings['author']}' → '{$seoData['author']}'";
                    
                    $changeText = empty($changes) ? 'Keine Änderungen' : implode(', ', $changes);
                    log_admin_action('settings_seo_update', "SEO-Einstellungen aktualisiert: {$changeText}");
                    $message = 'SEO-Einstellungen wurden erfolgreich aktualisiert';
                } else {
                    throw new Exception('Fehler beim Speichern der SEO-Einstellungen');
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Aktuelle SEO-Einstellungen laden
$seoSettings = get_seo_settings();

// Set page title and CSRF token for header
$pageTitle = 'Einstellungen - Admin - ' . APP_NAME;
$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-3 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-xl shadow-lg">
                    <i class="fas fa-cog text-white text-xl"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-slate-800">Einstellungen</h1>
                    <p class="text-slate-600">Verwalte die Website-Einstellungen</p>
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

        <!-- Settings Tabs -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Tab Navigation -->
            <div class="border-b border-slate-200 bg-slate-50">
                <nav class="flex">
                    <button id="seo-tab" class="tab-button active px-6 py-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600 bg-white">
                        <i class="fas fa-search mr-2"></i>
                        SEO-Einstellungen
                    </button>
                    <button id="general-tab" class="tab-button px-6 py-4 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300">
                        <i class="fas fa-globe mr-2"></i>
                        Allgemein
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-8">
                <!-- SEO Settings Tab -->
                <div id="seo-content" class="tab-content">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">SEO-Einstellungen</h2>
                        <p class="text-slate-600">Diese Einstellungen werden als Standard für alle Seiten verwendet, wenn keine spezifischen SEO-Daten gesetzt sind.</p>
                    </div>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_seo">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                        <!-- SEO Description -->
                        <div class="space-y-2">
                            <label for="seo_description" class="block text-sm font-semibold text-slate-700">
                                <i class="fas fa-file-alt mr-2 text-blue-500"></i>
                                Standard SEO-Beschreibung
                            </label>
                            <textarea 
                                id="seo_description" 
                                name="seo_description" 
                                rows="3" 
                                maxlength="500"
                                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                                placeholder="Beschreibe deine Website für Suchmaschinen..."
                            ><?php echo htmlspecialchars($seoSettings['description']); ?></textarea>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>Empfohlen: 120-160 Zeichen für optimale Darstellung in Suchergebnissen</span>
                                <span id="desc-counter">0/500</span>
                            </div>
                        </div>

                        <!-- SEO Keywords -->
                        <div class="space-y-2">
                            <label for="seo_keywords" class="block text-sm font-semibold text-slate-700">
                                <i class="fas fa-tags mr-2 text-green-500"></i>
                                Standard SEO-Keywords
                            </label>
                            <textarea 
                                id="seo_keywords" 
                                name="seo_keywords" 
                                rows="2" 
                                maxlength="300"
                                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"
                                placeholder="Rezepte, Kochen, Backen, Essen..."
                            ><?php echo htmlspecialchars($seoSettings['keywords']); ?></textarea>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>Trenne Keywords mit Kommas. Verwende relevante Begriffe für deine Website.</span>
                                <span id="keywords-counter">0/300</span>
                            </div>
                        </div>

                        <!-- SEO Author -->
                        <div class="space-y-2">
                            <label for="seo_author" class="block text-sm font-semibold text-slate-700">
                                <i class="fas fa-user-edit mr-2 text-purple-500"></i>
                                Standard Autor
                            </label>
                            <input 
                                type="text" 
                                id="seo_author" 
                                name="seo_author" 
                                maxlength="100"
                                value="<?php echo htmlspecialchars($seoSettings['author']); ?>"
                                class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                                placeholder="Name des Website-Autors..."
                            >
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>Wird in den Meta-Tags als Autor angegeben</span>
                                <span id="author-counter">0/100</span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-6 border-t border-slate-200">
                            <button 
                                type="submit" 
                                class="px-8 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white font-semibold rounded-lg shadow-lg hover:from-blue-600 hover:to-indigo-700 transform hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <i class="fas fa-save mr-2"></i>
                                SEO-Einstellungen speichern
                            </button>
                        </div>
                    </form>
                </div>

                <!-- General Settings Tab (Placeholder) -->
                <div id="general-content" class="tab-content hidden">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-slate-800 mb-2">Allgemeine Einstellungen</h2>
                        <p class="text-slate-600">Weitere Einstellungen werden hier in Zukunft verfügbar sein.</p>
                    </div>
                    
                    <div class="text-center py-12">
                        <i class="fas fa-tools text-6xl text-slate-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-slate-600 mb-2">In Entwicklung</h3>
                        <p class="text-slate-500">Weitere Einstellungsoptionen werden bald hinzugefügt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.id.replace('-tab', '-content');
            
            // Remove active class from all tabs
            tabButtons.forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600', 'bg-white');
                btn.classList.add('border-transparent', 'text-slate-500');
            });
            
            // Add active class to clicked tab
            this.classList.add('active', 'border-blue-500', 'text-blue-600', 'bg-white');
            this.classList.remove('border-transparent', 'text-slate-500');
            
            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            // Show target content
            document.getElementById(targetId).classList.remove('hidden');
        });
    });
    
    // Character counters
    function updateCounter(inputId, counterId, maxLength) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        
        function update() {
            const length = input.value.length;
            counter.textContent = `${length}/${maxLength}`;
            
            if (length > maxLength * 0.9) {
                counter.classList.add('text-red-500');
                counter.classList.remove('text-slate-500');
            } else {
                counter.classList.remove('text-red-500');
                counter.classList.add('text-slate-500');
            }
        }
        
        input.addEventListener('input', update);
        update(); // Initial update
    }
    
    updateCounter('seo_description', 'desc-counter', 500);
    updateCounter('seo_keywords', 'keywords-counter', 300);
    updateCounter('seo_author', 'author-counter', 100);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
