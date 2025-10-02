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
    <!-- Admin Sidebar Toggle Button (Fixed Position) -->
    <button id="admin-sidebar-toggle" 
            class="fixed top-[85px] right-2 z-[60] flex items-center justify-center w-12 h-12 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 group"
            aria-label="Admin-Panel öffnen"
            title="Admin-Panel">
        <i class="fas fa-crown text-lg group-hover:scale-110 transition-transform duration-200"></i>
                    </button>
                    
    <!-- Admin Sidebar -->
    <div id="admin-sidebar" class="fixed top-4 right-[0] w-80 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 border border-slate-700 rounded-xl shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out z-50" style="max-height: calc(100vh - 2rem);">
        <!-- Sidebar Header -->
        <div class="flex items-center justify-between p-4 border-b border-slate-700 bg-gradient-to-r from-slate-800 to-slate-700 rounded-t-xl">
            <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 rounded-lg shadow-lg">
                        <i class="fas fa-crown text-white text-sm"></i>
                        <span class="text-white font-bold text-sm tracking-wide">ADMIN</span>
                </div>
                                    </div>
            <button id="admin-sidebar-close" 
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-white hover:bg-slate-600 transition-colors"
                    aria-label="Admin-Panel schließen">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
        <!-- Sidebar Content -->
        <div class="flex flex-col" style="height: calc(100% - 73px);">
            <!-- Navigation Links -->
            <div class="flex-1 p-3 space-y-1 overflow-y-auto">
                <!-- Dashboard -->
                <a href="/admin/dashboard.php" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/dashboard.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <i class="fas fa-chart-line w-4 text-sm"></i>
                    <span class="font-medium text-sm">Dashboard</span>
                </a>
                
                <!-- Categories -->
                <a href="/admin/categories.php" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/categories.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <i class="fas fa-tags w-4 text-sm"></i>
                    <span class="font-medium text-sm">Kategorien</span>
                </a>
                
                <!-- Users -->
                <a href="/admin/users.php" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/users.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-users w-4 text-sm"></i>
                        <span class="font-medium text-sm">Benutzer</span>
                    </div>
                    <?php if ($quickStats['users'] > 0): ?>
                        <span class="bg-blue-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1.5">
                            <?= $quickStats['users'] > 99 ? '99+' : $quickStats['users'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Recipes -->
                <a href="/admin/recipes.php" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/recipes.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-utensils w-4 text-sm"></i>
                        <span class="font-medium text-sm">Rezepte</span>
                    </div>
                    <?php if ($quickStats['recipes'] > 0): ?>
                        <span class="bg-green-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1.5">
                            <?= $quickStats['recipes'] > 99 ? '99+' : $quickStats['recipes'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Comments -->
                <a href="/admin/comments.php" 
                   class="flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/comments.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-comments w-4 text-sm"></i>
                        <span class="font-medium text-sm">Kommentare</span>
                    </div>
                    <?php if ($quickStats['comments'] > 0): ?>
                        <span class="bg-orange-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1.5">
                            <?= $quickStats['comments'] > 9 ? '9+' : $quickStats['comments'] ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <!-- Divider -->
                <div class="border-t border-slate-600 my-3"></div>
                
                <!-- Settings -->
                <a href="/admin/settings.php" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 <?= str_ends_with($_SERVER['PHP_SELF'], '/admin/settings.php') 
                       ? 'bg-emerald-600 text-white shadow-lg' 
                       : 'text-slate-300 hover:bg-slate-700 hover:text-white' ?>">
                    <i class="fas fa-cog w-4 text-sm"></i>
                    <span class="font-medium text-sm">Einstellungen</span>
                </a>

                <!-- System Logs -->
                <a href="/admin/logs.php" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 text-slate-300 hover:bg-slate-700 hover:text-white">
                    <i class="fas fa-file-alt w-4 text-sm"></i>
                    <span class="font-medium text-sm">System-Logs</span>
                </a>
                
                <!-- Notifications -->
                <a href="/admin/notifications.php" 
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all duration-200 text-slate-300 hover:bg-slate-700 hover:text-white">
                    <i class="fas fa-bell w-4 text-sm"></i>
                    <span class="font-medium text-sm">Benachrichtigungen</span>
                </a>
            </div>
            
            <!-- Sidebar Footer -->
            <div class="p-3 border-t border-slate-700 bg-slate-800/50 space-y-2 rounded-b-xl">
                <a href="/recipe_new.php" 
                   class="flex items-center justify-center gap-2 w-full px-3 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors shadow-lg">
                    <i class="fas fa-plus text-sm"></i>
                    <span class="font-medium text-sm">Neues Rezept</span>
                </a>
                <a href="/" 
                   class="flex items-center justify-center gap-2 w-full px-3 py-2 text-slate-400 hover:text-slate-300 hover:bg-slate-700 rounded-lg transition-colors">
                    <i class="fas fa-external-link-alt text-sm"></i>
                    <span class="text-sm">Zur Website</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Sidebar Backdrop -->
    <div id="admin-sidebar-backdrop" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden transition-opacity duration-300"></div>


    

    <!-- Admin Sidebar JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Elements
            const sidebarToggle = document.getElementById('admin-sidebar-toggle');
            const sidebar = document.getElementById('admin-sidebar');
            const sidebarClose = document.getElementById('admin-sidebar-close');
            const sidebarBackdrop = document.getElementById('admin-sidebar-backdrop');

            // State management
            let sidebarOpen = false;

            // Open Sidebar Function
            function openSidebar() {
                if (sidebar && sidebarBackdrop) {
                    sidebarOpen = true;
                    sidebarBackdrop.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                    
                    // Add backdrop opacity
                    setTimeout(() => {
                        sidebarBackdrop.style.opacity = '1';
                    }, 10);
                    
                    // Slide in sidebar
                    setTimeout(() => {
                        sidebar.classList.remove('translate-x-full');
                    }, 50);
                    
                    // Update toggle button
                    if (sidebarToggle) {
                        sidebarToggle.setAttribute('aria-label', 'Admin-Panel schließen');
                        sidebarToggle.style.transform = 'scale(0.9)';
                    }
                }
            }

            // Close Sidebar Function
            function closeSidebar() {
                if (sidebar && sidebarBackdrop) {
                    sidebarOpen = false;
                    
                    // Slide out sidebar
                    sidebar.classList.add('translate-x-full');
                    
                    // Fade out backdrop
                    sidebarBackdrop.style.opacity = '0';
                    
                    // Clean up after animation
                    setTimeout(() => {
                        sidebarBackdrop.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    }, 300);
                    
                    // Reset toggle button
                    if (sidebarToggle) {
                        sidebarToggle.setAttribute('aria-label', 'Admin-Panel öffnen');
                        sidebarToggle.style.transform = 'scale(1)';
                    }
                }
            }

            // Toggle Sidebar Function
            function toggleSidebar() {
                if (sidebarOpen) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            }

            // Event Listeners
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    toggleSidebar();
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeSidebar();
                });
            }

            // Close sidebar when clicking on backdrop
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', (e) => {
                    if (e.target === sidebarBackdrop) {
                        closeSidebar();
                    }
                });
            }

            // Close sidebar on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && sidebarOpen) {
                    closeSidebar();
                }
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                // Close sidebar on mobile if window gets too small
                if (window.innerWidth < 768 && sidebarOpen) {
                    closeSidebar();
                }
            });

            // Prevent sidebar from closing when clicking inside it
            if (sidebar) {
                sidebar.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
            }

            // Add smooth hover effects to toggle button
            if (sidebarToggle) {
                sidebarToggle.addEventListener('mouseenter', () => {
                    if (!sidebarOpen) {
                        sidebarToggle.style.transform = 'scale(1.1)';
                    }
                });

                sidebarToggle.addEventListener('mouseleave', () => {
                    if (!sidebarOpen) {
                        sidebarToggle.style.transform = 'scale(1)';
                    }
                });
            }

            // Initialize backdrop styles
            if (sidebarBackdrop) {
                sidebarBackdrop.style.opacity = '0';
            }

            // Dynamic height adjustment
            function adjustSidebarHeight() {
                if (sidebar) {
                    const viewportHeight = window.innerHeight;
                    const sidebarTop = 16; // top-4 = 1rem = 16px
                    const sidebarBottom = 16; // bottom margin
                    const maxHeight = viewportHeight - sidebarTop - sidebarBottom;
                    
                    sidebar.style.maxHeight = `${maxHeight}px`;
                    
                    // Also adjust the content area
                    const sidebarContent = sidebar.querySelector('.flex.flex-col');
                    const header = sidebar.querySelector('.flex.items-center.justify-between');
                    if (sidebarContent && header) {
                        const headerHeight = header.offsetHeight;
                        sidebarContent.style.height = `${maxHeight - headerHeight}px`;
                    }
                }
            }

            // Adjust height on load and resize
            adjustSidebarHeight();
            window.addEventListener('resize', adjustSidebarHeight);
        });
    </script>
<?php endif; ?>