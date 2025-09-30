<?php if (is_admin()): ?>
    <!-- Admin Navigation Bar -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 border-b border-slate-700 shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-4">
            <nav class="flex items-center justify-between h-16">
                <!-- Left Side - Admin Branding -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 rounded-lg shadow-md">
                        <i class="fas fa-crown text-white text-sm"></i>
                        <span class="text-white font-bold text-sm tracking-wide">ADMIN</span>
                    </div>
                    <div class="hidden md:block h-6 w-px bg-slate-600"></div>
                    <span class="hidden md:block text-slate-300 text-sm font-medium">Verwaltungsbereich</span>
                </div>

                <!-- Center - Main Navigation -->
                <div class="flex items-center gap-2">
                    <!-- Dashboard -->
                    <a href="/admin/dashboard.php" 
                       class="group flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/dashboard.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>">
                        <i class="fas fa-chart-line text-sm"></i>
                        <span class="hidden lg:inline font-medium">Dashboard</span>
                    </a>

                    <!-- Categories -->
                    <a href="/admin/categories.php" 
                       class="group flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/categories.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>">
                        <i class="fas fa-tags text-sm"></i>
                        <span class="hidden lg:inline font-medium">Kategorien</span>
                    </a>

                    <!-- Users -->
                    <a href="/admin/users.php" 
                       class="group flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/users.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>">
                        <i class="fas fa-users text-sm"></i>
                        <span class="hidden lg:inline font-medium">Benutzer</span>
                    </a>

                    <!-- Recipes -->
                    <a href="/admin/recipes.php" 
                       class="group flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 
                              <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/recipes.php') 
                                  ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/50' 
                                  : 'text-slate-300 hover:text-white hover:bg-slate-700/50' ?>">
                        <i class="fas fa-utensils text-sm"></i>
                        <span class="hidden lg:inline font-medium">Rezepte</span>
                    </a>

                    <!-- More Dropdown -->
                    <div class="relative">
                        <button id="admin-more-btn" 
                                class="group flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 text-slate-300 hover:text-white hover:bg-slate-700/50"
                                aria-label="Weitere Optionen"
                                aria-expanded="false">
                            <i class="fas fa-ellipsis-h text-sm"></i>
                            <span class="hidden lg:inline font-medium">Mehr</span>
                            <i class="fas fa-chevron-down text-xs transition-transform duration-200" id="admin-more-icon"></i>
                        </button>

                        <!-- Dropdown Menu -->
                        <div id="admin-more-dropdown" 
                             class="hidden absolute right-0 mt-2 w-56 bg-slate-800 border border-slate-700 rounded-lg shadow-2xl overflow-hidden z-50">
                            <div class="py-1">
                                <a href="/admin/comments.php" 
                                   class="flex items-center gap-3 px-4 py-2.5 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                    <i class="fas fa-comments w-5"></i>
                                    <span>Kommentare</span>
                                </a>
                                <a href="/admin/notifications.php" 
                                   class="flex items-center gap-3 px-4 py-2.5 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                    <i class="fas fa-bell w-5"></i>
                                    <span>Benachrichtigungen</span>
                                </a>
                                <div class="border-t border-slate-700 my-1"></div>
                                <a href="/admin/settings.php" 
                                   class="flex items-center gap-3 px-4 py-2.5 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                    <i class="fas fa-cog w-5"></i>
                                    <span>Einstellungen</span>
                                </a>
                                <a href="/admin/logs.php" 
                                   class="flex items-center gap-3 px-4 py-2.5 text-slate-300 hover:bg-slate-700 hover:text-white transition-colors">
                                    <i class="fas fa-file-alt w-5"></i>
                                    <span>System-Logs</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Quick Actions -->
                <div class="flex items-center gap-2">
                    <!-- Back to Site -->
                    <a href="/" 
                       class="flex items-center gap-2 px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700/50 transition-all duration-200"
                       title="Zur Website">
                        <i class="fas fa-arrow-left text-sm"></i>
                        <span class="hidden md:inline text-sm">Zur Website</span>
                    </a>

                    <!-- Mobile Menu Toggle -->
                    <button id="admin-mobile-toggle" 
                            class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700/50 transition-all duration-200"
                            aria-label="Admin-Menü öffnen">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                </div>
            </nav>
        </div>
    </div>

    <!-- Mobile Admin Menu -->
    <div id="admin-mobile-menu" 
         class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 lg:hidden hidden transition-opacity duration-300">
        <div class="fixed inset-y-0 right-0 w-full max-w-sm bg-slate-900 shadow-2xl transform transition-transform duration-300 translate-x-full"
             id="admin-mobile-panel">
            <!-- Mobile Header -->
            <div class="flex items-center justify-between p-4 border-b border-slate-700">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 rounded-lg">
                        <i class="fas fa-crown text-white text-sm"></i>
                        <span class="text-white font-bold text-sm">ADMIN</span>
                    </div>
                </div>
                <button id="admin-mobile-close" 
                        class="flex items-center justify-center w-10 h-10 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-all duration-200"
                        aria-label="Menü schließen">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Mobile Navigation -->
            <nav class="flex flex-col p-4 space-y-2 overflow-y-auto max-h-[calc(100vh-80px)]">
                <a href="/admin/dashboard.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-chart-line w-5"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="/admin/categories.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-tags w-5"></i>
                    <span class="font-medium">Kategorien</span>
                </a>
                <a href="/admin/users.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-users w-5"></i>
                    <span class="font-medium">Benutzer</span>
                </a>
                <a href="/admin/recipes.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-utensils w-5"></i>
                    <span class="font-medium">Rezepte</span>
                </a>
                
                <div class="border-t border-slate-700 my-2"></div>
                
                <a href="/admin/comments.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-comments w-5"></i>
                    <span class="font-medium">Kommentare</span>
                </a>
                <a href="/admin/notifications.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-bell w-5"></i>
                    <span class="font-medium">Benachrichtigungen</span>
                </a>
                
                <div class="border-t border-slate-700 my-2"></div>
                
                <a href="/admin/settings.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-cog w-5"></i>
                    <span class="font-medium">Einstellungen</span>
                </a>
                <a href="/admin/logs.php" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-slate-300 hover:text-white hover:bg-slate-700 transition-colors">
                    <i class="fas fa-file-alt w-5"></i>
                    <span class="font-medium">System-Logs</span>
                </a>
                
                <div class="border-t border-slate-700 my-2"></div>
                
                <a href="/" 
                   class="flex items-center gap-3 px-4 py-3 rounded-lg text-emerald-400 hover:text-emerald-300 hover:bg-slate-700 transition-colors">
                    <i class="fas fa-arrow-left w-5"></i>
                    <span class="font-medium">Zur Website</span>
                </a>
            </nav>
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