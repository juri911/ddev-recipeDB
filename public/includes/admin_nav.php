<?php 
if (is_admin()): 
    // Get quick stats for badges
    try {
        $quickStats = [
            'users' => (int)db_query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'recipes' => (int)db_query('SELECT COUNT(*) FROM recipes')->fetchColumn(),
            'comments' => (int)db_query('SELECT COUNT(*) FROM recipe_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)')->fetchColumn(),
            'pending' => 0 // Placeholder for pending items
        ];
    } catch (Exception $e) {
        $quickStats = ['users' => 0, 'recipes' => 0, 'comments' => 0, 'pending' => 0];
    }
?>
    <!-- Admin Navigation Bar -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700 shadow-xl sticky top-2 z-50 h-[80px]">
        <div class="container mx-auto px-4">
            <nav class="flex items-center justify-between h-16">
                <!-- Left Side - Admin Branding & Mobile Toggle -->
                <div class="flex items-center gap-4">
                    <!-- Mobile Menu Toggle -->
                    <button id="admin-mobile-toggle" 
                            class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700/50 transition-colors"
                            aria-label="Admin-Menü öffnen">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    
                    <!-- Admin Badge -->
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 rounded-lg shadow-lg">
                        <i class="fas fa-crown text-white text-sm"></i>
                        <span class="text-white font-bold text-sm tracking-wide">ADMIN</span>
                    </div>
                    
                    <!-- Separator & Title -->
                    <div class="hidden lg:flex items-center gap-4">
                        <div class="h-6 w-px bg-slate-600"></div>
                        <span class="text-slate-300 text-sm font-medium">Verwaltungsbereich</span>
                    </div>
                </div>

                <!-- Center - Main Navigation (Desktop) -->
                <div class="hidden lg:flex items-center gap-1">
                    <!-- Dashboard -->
                    <a href="/admin/dashboard.php" 
                       class="group relative flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/dashboard.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>"
                       title="Dashboard">
                        <i class="fas fa-chart-line text-sm"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>

                    <!-- Categories -->
                    <a href="/admin/categories.php" 
                       class="group relative flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/categories.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>"
                       title="Kategorien verwalten">
                        <i class="fas fa-tags text-sm"></i>
                        <span class="font-medium">Kategorien</span>
                    </a>

                    <!-- Users -->
                    <a href="/admin/users.php" 
                       class="group relative flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/users.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>"
                       title="<?= $quickStats['users'] ?> Benutzer verwalten">
                        <i class="fas fa-users text-sm"></i>
                        <span class="font-medium">Benutzer</span>
                        <?php if ($quickStats['users'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">
                                <?= $quickStats['users'] > 99 ? '99+' : $quickStats['users'] ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- Recipes -->
                    <a href="/admin/recipes.php" 
                       class="group relative flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/recipes.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>"
                       title="<?= $quickStats['recipes'] ?> Rezepte verwalten">
                        <i class="fas fa-utensils text-sm"></i>
                        <span class="font-medium">Rezepte</span>
                        <?php if ($quickStats['recipes'] > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-green-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">
                                <?= $quickStats['recipes'] > 99 ? '99+' : $quickStats['recipes'] ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- More Dropdown -->
                    <div class="relative">
                        <button id="admin-more-btn" 
                                class="group relative flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 text-slate-300 hover:text-white hover:bg-slate-700/50"
                                aria-label="Weitere Optionen"
                                aria-expanded="false"
                                title="Weitere Optionen">
                            <i class="fas fa-ellipsis-h text-sm"></i>
                            <span class="font-medium">Mehr</span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" id="admin-more-icon"></i>
                            <?php if ($quickStats['comments'] > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-orange-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">
                                    <?= $quickStats['comments'] > 9 ? '9+' : $quickStats['comments'] ?>
                                </span>
                            <?php endif; ?>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="admin-more-dropdown" 
                             class="hidden absolute right-0 mt-2 w-64 bg-slate-800 border border-slate-700 rounded-lg shadow-2xl overflow-hidden z-[999999]">
                            <div class="py-2">
                                <a href="/admin/comments.php" 
                                   class="flex items-center justify-between px-4 py-3 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/comments.php') 
                                       ? 'bg-slate-700 text-white' 
                                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-comments w-5"></i>
                                        <span>Kommentare</span>
                                    </div>
                                    <?php if ($quickStats['comments'] > 0): ?>
                                        <span class="bg-orange-500 text-white text-xs rounded-full min-w-[20px] h-[20px] flex items-center justify-center px-2">
                                            <?= $quickStats['comments'] ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                                <div class="border-t border-slate-700 my-1"></div>
                                <a href="/admin/settings.php" 
                                   class="flex items-center gap-3 px-4 py-3 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/settings.php') 
                                       ? 'bg-slate-700 text-white' 
                                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                                    <i class="fas fa-cog w-5"></i>
                                    <span>Einstellungen</span>
                                </a>
                                <a href="/admin/logs.php" 
                                   class="flex items-center gap-3 px-4 py-3 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                    <i class="fas fa-file-alt w-5"></i>
                                    <span>System-Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Quick Actions -->
                <div class="hidden lg:flex items-center gap-2">
                    <!-- Quick Add Recipe -->
                    <a href="/recipe_new.php" 
                       class="flex items-center gap-2 px-3 py-2 text-emerald-400 hover:text-emerald-300 hover:bg-slate-700/50 rounded-lg transition-colors"
                       title="Neues Rezept erstellen">
                        <i class="fas fa-plus text-sm"></i>
                        <span class="text-sm font-medium">Rezept</span>
                    </a>
                    
                    <!-- Back to Site -->
                    <a href="/" 
                       class="flex items-center gap-2 px-3 py-2 text-slate-400 hover:text-slate-300 hover:bg-slate-700/50 rounded-lg transition-colors"
                       title="Zur Website">
                        <i class="fas fa-external-link-alt text-sm"></i>
                        <span class="text-sm font-medium">Website</span>
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div id="admin-mobile-menu" class="lg:hidden hidden fixed inset-0 z-50">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm"></div>
        
        <!-- Slide Panel -->
        <div id="admin-mobile-panel" class="fixed right-0 top-0 h-full w-80 bg-slate-900 border-l border-slate-700 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 border-b border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2 px-2 py-1 bg-gradient-to-r from-amber-500 to-orange-500 rounded-lg">
                        <i class="fas fa-crown text-white text-sm"></i>
                        <span class="text-white font-bold text-sm">ADMIN</span>
                    </div>
                </div>
                <button id="admin-mobile-close" 
                        class="flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition-colors"
                        aria-label="Menü schließen">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <!-- Navigation Links -->
            <div class="p-4 space-y-2">
                <!-- Dashboard -->
                <a href="/admin/dashboard.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/dashboard.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <i class="fas fa-chart-line w-5"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                
                <!-- Categories -->
                <a href="/admin/categories.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/categories.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <i class="fas fa-tags w-5"></i>
                    <span class="font-medium">Kategorien</span>
                </a>
                
                <!-- Users -->
                <a href="/admin/users.php" 
                   class="flex items-center justify-between px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/users.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-users w-5"></i>
                        <span class="font-medium">Benutzer</span>
                    </div>
                    <?php if ($quickStats['users'] > 0): ?>
                        <span class="bg-blue-500 text-white text-xs rounded-full min-w-[20px] h-[20px] flex items-center justify-center px-2">
                            <?= $quickStats['users'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Recipes -->
                <a href="/admin/recipes.php" 
                   class="flex items-center justify-between px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/recipes.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-utensils w-5"></i>
                        <span class="font-medium">Rezepte</span>
                    </div>
                    <?php if ($quickStats['recipes'] > 0): ?>
                        <span class="bg-green-500 text-white text-xs rounded-full min-w-[20px] h-[20px] flex items-center justify-center px-2">
                            <?= $quickStats['recipes'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Comments -->
                <a href="/admin/comments.php" 
                   class="flex items-center justify-between px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/comments.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-comments w-5"></i>
                        <span class="font-medium">Kommentare</span>
                    </div>
                    <?php if ($quickStats['comments'] > 0): ?>
                        <span class="bg-orange-500 text-white text-xs rounded-full min-w-[20px] h-[20px] flex items-center justify-center px-2">
                            <?= $quickStats['comments'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Divider -->
                <div class="border-t border-slate-700 my-4"></div>
                
                <!-- Settings -->
                <a href="/admin/settings.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/settings.php') 
                       ? 'bg-emerald-600 text-white' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?> transition-colors">
                    <i class="fas fa-cog w-5"></i>
                    <span class="font-medium">Einstellungen</span>
                </a>
                
                <!-- Notifications -->
                <a href="/admin/notifications.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-bell w-5"></i>
                    <span class="font-medium">Benachrichtigungen</span>
                </a>
                
                <!-- System Logs -->
                <a href="/admin/logs.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                    <i class="fas fa-file-alt w-5"></i>
                    <span class="font-medium">System-Logs</span>
                </a>
            </div>
            
            <!-- Footer Actions -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-slate-700 space-y-2">
                <a href="/recipe_new.php" 
                   class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors">
                    <i class="fas fa-plus"></i>
                    <span class="font-medium">Neues Rezept</span>
                </a>
                <a href="/" 
                   class="flex items-center justify-center gap-2 w-full px-4 py-2 text-slate-400 hover:text-slate-300 hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Zur Website</span>
                </a>
            </div>
        </div>
    </div>

    

    <!-- Admin Navigation JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // More Dropdown Toggle
            const moreBtn = document.getElementById('admin-more-btn');
            const moreDropdown = document.getElementById('admin-more-dropdown');
            const moreIcon = document.getElementById('admin-more-icon');

            if (moreBtn && moreDropdown) {
                moreBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = moreDropdown.classList.contains('hidden');
                    
                    moreDropdown.classList.toggle('hidden');
                    moreBtn.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                    
                    if (moreIcon) {
                        moreIcon.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', (e) => {
                    if (!moreBtn.contains(e.target) && !moreDropdown.contains(e.target)) {
                        moreDropdown.classList.add('hidden');
                        moreBtn.setAttribute('aria-expanded', 'false');
                        if (moreIcon) {
                            moreIcon.style.transform = 'rotate(0deg)';
                        }
                    }
                });
            }

            // Mobile Menu Toggle
            const mobileToggle = document.getElementById('admin-mobile-toggle');
            const mobileMenu = document.getElementById('admin-mobile-menu');
            const mobilePanel = document.getElementById('admin-mobile-panel');
            const mobileClose = document.getElementById('admin-mobile-close');

            function openMobileMenu() {
                if (mobileMenu && mobilePanel) {
                    mobileMenu.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                    setTimeout(() => {
                        mobilePanel.classList.remove('translate-x-full');
                    }, 10);
                }
            }

            function closeMobileMenu() {
                if (mobileMenu && mobilePanel) {
                    mobilePanel.classList.add('translate-x-full');
                    setTimeout(() => {
                        mobileMenu.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }, 300);
                }
            }

            if (mobileToggle) {
                mobileToggle.addEventListener('click', openMobileMenu);
            }

            if (mobileClose) {
                mobileClose.addEventListener('click', closeMobileMenu);
            }

            if (mobileMenu) {
                mobileMenu.addEventListener('click', (e) => {
                    if (e.target === mobileMenu) {
                        closeMobileMenu();
                    }
                });
            }

            // Close mobile menu on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    closeMobileMenu();
                }
            });
        });
    </script>
<?php endif; ?>