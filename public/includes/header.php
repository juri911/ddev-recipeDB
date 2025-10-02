<?php
// Config einbinden
require_once __DIR__ . '/../../config.php';
// Flash Messages einbinden
require_once __DIR__ . '/../../lib/flash.php';
// Global header for all pages
// This file should be included at the beginning of each page after session and auth setup

if (!isset($user)) {
    if (function_exists('current_user')) {
        $user = current_user();
    } else {
        // Fallback: versuchen, die auth.php zu laden
        $auth_file = __DIR__ . '/../../lib/auth.php';
        if (file_exists($auth_file)) {
            require_once $auth_file;
            if (function_exists('current_user')) {
                $user = current_user();
            } else {
                $user = null; // Fallback
            }
        } else {
            $user = null; // Fallback
        }
    }
}

// Load SEO settings from database
require_once __DIR__ . '/../../lib/settings.php';
require_once __DIR__ . '/../../lib/users.php';
require_once __DIR__ . '/../../lib/logger.php';

// Log page visit (only for non-AJAX requests)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    $page_name = basename($_SERVER['PHP_SELF'], '.php');
    if (!in_array($page_name, ['api', 'ajax'])) { // Skip API endpoints
        log_page_visit($page_name);
    }
}
$seoSettings = get_seo_settings();

// SEO Defaults – können pro Seite überschrieben werden
$seo = [
    'title' => $seo['title'] ?? $pageTitle ?? APP_NAME,
    'description' => $seo['description'] ?? $pageDescription ?? $seoSettings['description'],
    'keywords' => $seo['keywords'] ?? $pageKeywords ?? $seoSettings['keywords'],
    'author' => $seo['author'] ?? $pageAuthor ?? $seoSettings['author'],
    'url' => $seo['url'] ?? $pageUrl ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'image' => $seo['image'] ?? $pageImage ?? '/assets/default_og.png',
    'jsonLd' => $seo['jsonLd'] ?? null,
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo['title']); ?></title>

    <!-- SEO -->
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="<?php echo htmlspecialchars($seo['author']); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($seo['url']); ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($seo['url']); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo['image']); ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="<?php echo htmlspecialchars($seo['url']); ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($seo['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo['image']); ?>">

    <!-- Tailwind + Fonts -->
    <script src="/assets/css/tailwindV4.css"></script>
    <link rel="stylesheet" href="/assets/fonts/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">

    <?php if (isset($csrfToken)): ?>
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <?php endif; ?>
    <?php if ($user): ?>
        <meta name="user-avatar"
            content="<?php echo htmlspecialchars(isset($user['avatar_path']) && $user['avatar_path'] ? absolute_url_from_path((string) $user['avatar_path']) : '/images/default_avatar.png'); ?>">
        <meta name="user-name" content="<?php echo htmlspecialchars($user['name']); ?>">
    <?php endif; ?>
</head>

<body>

    <!-- Top gradient -->
    <div class="w-full h-[6px] bg-gradient-to-r from-[#2d7ef7] to-fuchsia-400 fixed top-0 z-[99999]"></div>

    <!-- Mobile Header (≤1024px) -->
    <header id="mobile-header" class="mobile-header hidden lg:hidden items-center justify-between px-4 py-3">
        <!-- Logo -->
        <div class="flex items-center">
            <a href="/" class="flex items-center">
                <svg fill="currentColor" width="120px" height="35px" style="color: var(--rh-primary);">
                    <use href="#logo"></use>
                </svg>
                <span class="ml-2 text-xs px-2 py-1 rounded bg-[var(--rh-primary)] text-white">beta</span>
            </a>
        </div>

        <!-- Mobile Actions -->
        <div class="flex items-center gap-0">
            <!-- Theme Toggle -->
            <button id="mobile-theme-toggle" class="p-2 rounded-lg transition-colors" style="color: var(--rh-text);"
                aria-label="Theme wechseln">
                <i class="fas fa-moon text-lg"></i>
            </button>

            <!-- Search -->
            <button popovertarget="search-popover" class="p-2 rounded-lg transition-colors"
                style="color: var(--rh-text);" aria-label="Suchen">
                <i class="fas fa-magnifying-glass text-lg"></i>
            </button>

            <!-- Card Magnet Toggle -->
            <button id="mobile-magnet-toggle" class="p-2 rounded-lg transition-colors magnet-rotate" style="color: var(--rh-text);"
                aria-label="Card Magnet Modus" title="Card Magnet Modus">
                <i class="fas fa-magnet text-lg transition-transform duration-300 ease-in-out"></i>
            </button>
            <?php if (isset($user) && $user): ?>
            <!-- Notifications -->
            <?php
                    // Ensure function exists before calling
                    $unreadCount = 0;
                    if (function_exists('count_unread_notifications')) {
                        $unreadCount = count_unread_notifications((int) $user['id']);
                    }
                    ?>
                    <button popovertarget="notification-bell"
                        class=" lg:p-3 p-2 lg:rounded-xl rounded-none transition-all duration-200  relative">
                        <i class="fa-solid fa-bell lg:text-lg text-lg"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span id="notification-badge"
                                class="absolute -top-1 -right-1 text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center text-white animate-pulse"
                                style="background: linear-gradient(135deg, #ef4444, #ec4899); box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);">
                                <?php echo $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                        <?php endif; ?>          
            <!-- Mobile Menu -->
            <button id="mobile-menu-toggle" class="p-2 rounded-lg transition-colors" style="color: var(--rh-text);"
                aria-label="Menü">
                <i class="fas fa-bars text-lg"></i>
            </button>
        </div>
    </header>

    <!-- Desktop Header -->
    <header
        class="flex lg:sticky lg:top-0 fixed bottom-0 left-0 right-0 z-10 container-fluid min-h-[80px] mx-auto justify-between items-center z-[40] backdrop-blur-sm boder border-b border-[var(--rh-border)]">
        <!-- Logo -->
        <div class="lg:flex hidden content-start relative">
            <a href="/" class="flex items-center z-[9999] origin-left pl-4 group">
                <?php //echo get_app_logo_html(); 
                ?>
                <svg fill="currentColor" width="160px" height="50px">
                    <use href="#logo"></use </svg>
                    <span class="ml-2 text-xs px-2 py-1 rounded bg-[var(--rh-primary)] text-white">beta</span>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="flex items-center justify-between lg:justify-end h-[75px] w-full">
            <div class="flex lg:justify-end justify-between w-full items-center gap-x-0 lg:gap-x-5 text-[16px]">
                <!-- Link group -->
                <!-- Home -->
                <a href="/" data-nav-link
                    class=" lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 ">
                    <i class="fa-solid fa-house lg:text-base text-2xl"></i>
                </a>
                <!-- Search -->
                <button popovertarget="search-popover"
                    class=" lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 ">
                    <i class="fas fa-magnifying-glass lg:text-base text-2xl"></i>
                </button>
                <!-- Categories -->
                <a href="/categories.php" data-nav-link
                    class=" lg:block hidden lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 ">
                    <i class="fa-solid fa-tags lg:text-base text-2xl"></i>
                </a>

                <!-- Theme Toggle Button -->
                <button id="theme-toggle"
                    class=" lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 "
                    aria-label="Theme wechseln" title="Theme wechseln">
                    <i class="fas fa-moon lg:text-base text-2xl"></i>
                    <span class="hidden lg:inline ml-2 font-medium">Dark</span>
                </button>

                <?php if (isset($user) && $user): ?>
                    <!-- New Recipe Button -->
                    <a href="/recipe_new.php" data-nav-link
                        class=" lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 "
                        style="background: linear-gradient(135deg, var(--rh-primary), #10b981); color: white; box-shadow: 0 4px 6px rgba(45, 126, 247, 0.25);">
                        <i class="fa-solid fa-feather lg:text-base text-2xl lg:mr-2"></i>
                        <span class="lg:inline hidden font-medium">Neues Rezept</span>
                    </a>
                    <!-- Notifications -->
                    <?php
                    // Ensure function exists before calling
                    $unreadCount = 0;
                    if (function_exists('count_unread_notifications')) {
                        $unreadCount = count_unread_notifications((int) $user['id']);
                    }
                    ?>
                    <button popovertarget="notification-bell"
                        class=" lg:p-3 p-4 lg:rounded-xl rounded-none transition-all duration-200  relative">
                        <i class="fa-solid fa-bell lg:text-lg text-2xl"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span id="notification-badge"
                                class="absolute -top-1 -right-1 text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center text-white animate-pulse"
                                style="background: linear-gradient(135deg, #ef4444, #ec4899); box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);">
                                <?php echo $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- User Dropdown -->
                    <div class="relative inline-block text-left ml-2">
                        <!-- Avatar Button -->
                        <button id="userMenuButton"
                            class=" flex items-center focus:outline-none lg:p-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 ">
                            <div class="h-10 w-10 rounded-full overflow-hidden transition-all duration-200 cursor-pointer"
                                style="border: 2px solid var(--rh-border); box-shadow: 0 2px 4px var(--rh-shadow);">
                                <img src="<?php echo htmlspecialchars(isset($user['avatar_path']) && $user['avatar_path'] ? absolute_url_from_path((string) $user['avatar_path']) : '/images/default_avatar.png'); ?>"
                                    class="h-10 w-10 rounded-full object-cover" alt="Avatar" />
                            </div>
                            <span class="ml-3 text-sm font-medium hidden lg:inline" style="color: var(--rh-text);">
                                <?php echo htmlspecialchars($user['name']); ?>
                            </span>
                            <i class="fas fa-chevron-down ml-2 text-xs hidden lg:inline"
                                style="color: var(--rh-muted);"></i>
                        </button>

                        <!-- Dropdown - Modern styling -->
                        <div id="userMenuDropdown"
                            class="hidden absolute right-0 w-56 rounded-xl z-50 transform transition-all duration-200 ease-out"
                            style="background: var(--rh-card-bg); border: 1px solid var(--rh-border); box-shadow: 0 10px 25px -5px var(--rh-shadow); backdrop-filter: blur(20px);">
                            <div class="p-2">
                                <a href="<?php echo htmlspecialchars(profile_url(['id' => $user['id'], 'name' => $user['name']])); ?>"
                                    data-nav-link
                                    class="flex items-center px-4 py-3 text-sm rounded-lg transition-all duration-200 hover:scale-[1.02]"
                                    style="color: var(--rh-text);"
                                    onmouseover="this.style.backgroundColor='var(--rh-hover-bg)'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                    <i class="fas fa-user mr-3 w-4"></i>
                                    <span class="font-medium">Profil ansehen</span>
                                </a>
                                <a href="/profile_edit.php" data-nav-link
                                    class="flex items-center px-4 py-3 text-sm rounded-lg transition-all duration-200 hover:scale-[1.02]"
                                    style="color: var(--rh-text);"
                                    onmouseover="this.style.backgroundColor='var(--rh-hover-bg)'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                    <i class="fas fa-edit mr-3 w-4"></i>
                                    <span class="font-medium">Profil bearbeiten</span>
                                </a>
                                <div class="my-2" style="border-top: 1px solid var(--rh-border);"></div>
                                <a href="/logout.php" data-nav-link
                                    class="flex items-center px-4 py-3 text-sm rounded-lg transition-all duration-200 hover:scale-[1.02]"
                                    style="color: #ef4444;"
                                    onmouseover="this.style.backgroundColor='rgba(239, 68, 68, 0.1)'"
                                    onmouseout="this.style.backgroundColor='transparent'">
                                    <i class="fa-solid fa-arrow-right-from-bracket mr-3 w-4 rotate-180"></i>
                                    <span class="font-medium">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>

                    <a href="/login.php" data-nav-link
                        class="w-auto flex items-center lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200 "
                        style="background: linear-gradient(135deg, var(--rh-primary), #8b5cf6); color: white; box-shadow: 0 4px 6px rgba(45, 126, 247, 0.25);">
                        <i class="fa-solid fa-arrow-right-from-bracket lg:text-base text-2xl lg:mr-2"></i>
                        <span class="lg:inline hidden font-medium">Log In</span>
                    </a>

                    <a href="/register.php" data-nav-link
                        class=" flex items-center lg:px-4 lg:py-2 p-4 lg:rounded-xl rounded-none transition-all duration-200  relative overflow-hidden">
                        <span class="lg:inline hidden font-medium mr-2">Registrieren</span>
                        <i class="fa-solid fa-pencil lg:text-base text-2xl"></i>
                        <div class="absolute bottom-0 left-0 h-0.5 bg-gradient-to-r from-blue-600 to-purple-600 transition-all duration-300 w-0 hover:w-full">
                        </div>
                    </a>

                <?php endif; ?>

                <!-- Mobile menu button -->
                <button id="mobile-nav-btn"
                    class="relative flex items-end justify-center mr-0 lg:mr-3  lg:inline hidden"
                    aria-label="Menü öffnen" aria-expanded="false" aria-controls="mobile-nav-panel">
                    <i
                        class="fas fa-bars text-[36px] lg:text-[var(--rh-primary)] hover:text-[var(--rh-text)] transition-all duration-300 ease-out  lg:p-0 p-4"></i>

                </button>
            </div>
        </nav>
    </header>

    <!-- Menu Overlay -->
    <div id="mobile-nav-overlay"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 z-40">
    </div>

    <!-- Drawer -->
    <nav id="mobile-nav-panel" class="fixed top-0 right-0 h-full w-full sm:w-[500px] bg-white text-[var(--rh-text-black)] shadow-2xl z-50 translate-x-full 
                transition-transform duration-300 ease-in-out will-change-transform flex flex-col" aria-hidden="true">
        <div class="flex items-center justify-end p-4">
            <button id="mobile-nav-close"
                class=" relative w-[46px] h-[46px] bg-[var(--rh-primary)] rounded-full flex flex-col items-center justify-center gap-1.5 active:scale-95 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                aria-label="Menü schließen" aria-expanded="false" aria-controls="mobile-nav-panel">
                <i class="fa-solid fa-xmark text-[26px]  transition-all duration-300 ease-out"></i>
            </button>
        </div>

        <ul class="flex-1 overflow-y-auto p-4 space-y-3 dark:text-black">
            <li><a href="/" data-mobile-nav-link
                    class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Startseite</a></li>
            <li><a href="/categories.php" data-mobile-nav-link
                    class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Rezepte</a></li>
            <li><a href="/blog.php" data-mobile-nav-link
                    class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Blog</a></li>
            <li><a href="/kontakt.php" data-mobile-nav-link
                    class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Kontakt</a></li>
        </ul>
    </nav>

    <!-- Notification PopOver -->
    <?php if (isset($user) && $user): ?>
        <div popover id="notification-bell" class="popover container mx-auto lg:max-w-4xl min-h-[50%] max-h-full z-[99]">
            <div class="popover-content-wrapper">
                <header class="popover-header">
                    <button popovertarget="notification-bell" popovertargetaction="hide" class="popover-close-btn"
                        aria-label="Close notifications" title="Close notifications">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"
                            fill="currentColor" aria-hidden="true">
                            <path
                                d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z">
                            </path>
                        </svg>
                    </button>
                </header>
                <section class="popover-section sm:px-[2rem] px-4 py-[1.5rem]">
                    <!-- Notification List -->
                    <div id="notification-list" class="space-y-3 max-h-96 overflow-y-auto mb-6">
                        <!-- Notifications will be loaded here -->
                        <div class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--rh-primary)]"></div>
                            <span class="ml-3 text-gray-600">Lade Benachrichtigungen...</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div
                        class="grid lg:grid-cols-2 lg:grid-rows-1 grid-cols-1 grid-rows-2 gap-4 pt-4 border-t border-gray-200">
                        <button id="mark-all-read"
                            class="block w-full px-4 py-3 rounded bg-blue-600 text-white text-center text-sm font-medium hover:bg-blue-700 transition-colors">
                            <i class="fas fa-check-double mr-2"></i>
                            Alle als gelesen markieren
                        </button>
                        <button id="delete-all-notifications"
                            class="w-full px-4 py-3 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors cursor-pointer">
                            <i class="fas fa-trash mr-2"></i>
                            Alle löschen
                        </button>
                    </div>
                </section>
            </div>
        </div>
    <?php endif; ?>

    <script src="/assets/js/theme.js"></script>
    <script src="/assets/js/magnet-rotate.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            // Mobile nav active
            function setActiveLinks() {
                const currentPath = window.location.pathname;
                const currentPage = currentPath.split('/').pop() || 'index.php';

                // Desktop navigation links
                const navLinks = document.querySelectorAll('[data-nav-link]');
                navLinks.forEach(link => {
                    const linkPath = link.getAttribute('href');
                    const linkPage = linkPath.split('/').pop() || 'index.php';

                    // Check if current page matches
                    if (currentPath === linkPath ||
                        (currentPath === '/' && linkPath === '/') ||
                        (currentPage === linkPage && linkPage !== '')) {
                        link.classList.add('nav-link-active');
                    } else {
                        link.classList.remove('nav-link-active');
                    }
                });

                // Mobile navigation links
                const mobileNavLinks = document.querySelectorAll('[data-mobile-nav-link]');
                mobileNavLinks.forEach(link => {
                    const linkPath = link.getAttribute('href');
                    const linkPage = linkPath.split('/').pop() || 'index.php';

                    if (currentPath === linkPath ||
                        (currentPath === '/' && linkPath === '/') ||
                        (currentPage === linkPage && linkPage !== '')) {
                        link.classList.add('mobile-nav-link-active');
                    } else {
                        link.classList.remove('mobile-nav-link-active');
                    }
                });
            }

            // Set active links on page load
            setActiveLinks();

            // Update active links when user clicks (for SPA-like behavior)
            document.addEventListener('click', function (e) {
                const clickedLink = e.target.closest('[data-nav-link], [data-mobile-nav-link]');
                if (clickedLink) {
                    // Small delay to allow navigation to complete
                    setTimeout(setActiveLinks, 100);
                }
            });
            // Mobile navigation
            const btn = document.getElementById("mobile-nav-btn");
            const panel = document.getElementById("mobile-nav-panel");
            const overlay = document.getElementById("mobile-nav-overlay");
            const closeBtn = document.getElementById("mobile-nav-close");

            let startX = 0;

            function openMenu() {
                if (!panel || !overlay || !btn) return;
                panel.classList.remove("translate-x-full");
                overlay.classList.remove("opacity-0", "pointer-events-none");
                btn.classList.add("open");
                btn.setAttribute("aria-expanded", "true");
                panel.setAttribute("aria-hidden", "false");
                document.body.classList.add("overflow-hidden");
            }

            function closeMenu() {
                if (!panel || !overlay || !btn) return;
                panel.classList.add("translate-x-full");
                overlay.classList.add("opacity-0", "pointer-events-none");
                btn.classList.remove("open");
                btn.setAttribute("aria-expanded", "false");
                panel.setAttribute("aria-hidden", "true");
                document.body.classList.remove("overflow-hidden");
            }

            if (btn) {
                btn.addEventListener("click", () => {
                    panel && panel.classList.contains("translate-x-full") ? openMenu() : closeMenu();
                });
            }

            if (overlay) overlay.addEventListener("click", closeMenu);
            if (closeBtn) closeBtn.addEventListener("click", closeMenu);

            // Swipe to close
            if (panel) {
                panel.addEventListener("touchstart", (e) => {
                    startX = e.touches[0].clientX;
                });
                panel.addEventListener("touchmove", (e) => {
                    let diff = e.touches[0].clientX - startX;
                    if (diff > 50) closeMenu();
                });
            }

            // User Dropdown Menu Functionality
            const userButton = document.getElementById("userMenuButton");
            const userDropdown = document.getElementById("userMenuDropdown");

            if (userButton && userDropdown) {
                // Funktion zur automatischen Positionierung des Dropdowns
                function positionDropdown() {
                    const buttonRect = userButton.getBoundingClientRect();
                    const dropdownRect = userDropdown.getBoundingClientRect();
                    const viewportHeight = window.innerHeight;
                    const spaceBelow = viewportHeight - buttonRect.bottom;
                    const spaceAbove = buttonRect.top;
                    const dropdownHeight = dropdownRect.height || 200; // Fallback-Höhe

                    // Klassen zurücksetzen
                    userDropdown.classList.remove('bottom-full', 'top-full', 'mb-2', 'mt-2');

                    // Entscheiden ob nach oben oder unten
                    if (spaceBelow >= dropdownHeight || spaceBelow >= spaceAbove) {
                        // Nach unten ausrichten
                        userDropdown.classList.add('top-full', 'mt-2');
                    } else {
                        // Nach oben ausrichten
                        userDropdown.classList.add('bottom-full', 'mb-2');
                    }
                }

                // Toggle Dropdown mit automatischer Positionierung
                userButton.addEventListener("click", (e) => {
                    e.stopPropagation();

                    if (userDropdown.classList.contains("hidden")) {
                        // Dropdown anzeigen
                        userDropdown.classList.remove("hidden");
                        // Kurz warten, damit das Element gerendert wird, dann positionieren
                        requestAnimationFrame(() => {
                            positionDropdown();
                        });
                    } else {
                        // Dropdown verstecken
                        userDropdown.classList.add("hidden");
                    }
                });

                // Bei Fenstergröße-Änderung neu positionieren
                window.addEventListener('resize', () => {
                    if (!userDropdown.classList.contains("hidden")) {
                        positionDropdown();
                    }
                });

                // Bei Scroll neu positionieren
                window.addEventListener('scroll', () => {
                    if (!userDropdown.classList.contains("hidden")) {
                        positionDropdown();
                    }
                });

                // Click outside closes dropdown
                document.addEventListener("click", (e) => {
                    if (!userButton.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.add("hidden");
                    }
                });
            }

            // Notification PopOver System
            const notificationPopover = document.getElementById('notification-bell');
            const notificationList = document.getElementById('notification-list');
            const markAllReadButton = document.getElementById('mark-all-read');
            const deleteAllButton = document.getElementById('delete-all-notifications');
            const notificationBadge = document.getElementById('notification-badge');

            async function fetchNotifications() {
                if (!notificationList) return;

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const headers = {
                        'Accept': 'application/json'
                    };

                    if (csrfToken) {
                        headers['X-CSRF-Token'] = csrfToken;
                    }

                    const response = await fetch('/api/get_notifications.php', {
                        method: 'GET',
                        headers: headers,
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        console.error('Response not OK:', response.status, response.statusText);
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const contentType = response.headers.get("content-type");
                    if (!contentType || !contentType.includes("application/json")) {
                        const text = await response.text();
                        console.error('Response is not JSON:', text);
                        throw new Error('Server returned non-JSON response');
                    }

                    const notifications = await response.json();

                    if (!Array.isArray(notifications)) {
                        console.error('Invalid notifications format:', notifications);
                        throw new Error('Invalid response format');
                    }

                    notificationList.innerHTML = '';
                    if (notifications.length === 0) {
                        notificationList.innerHTML = `
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <i class="fas fa-bell-slash text-4xl text-gray-300 mb-3"></i>
                                <p class="text-gray-500 text-lg">Keine Benachrichtigungen</p>
                                <p class="text-gray-400 text-sm mt-1">Du bist auf dem neuesten Stand!</p>
                            </div>
                        `;
                        return;
                    }

                    notifications.forEach(n => {
                        const notificationItem = document.createElement('div');
                        notificationItem.className = `p-4 border rounded-lg transition-colors hover:bg-gray-50 ${n.is_read ? 'bg-gray-50 border-gray-200' : 'bg-white border-blue-200 shadow-sm'}`;

                        let link = '';
                        let icon = 'fa-bell';

                        if (n.type === 'new_recipe' && n.entity_id) {
                            link = `<a href="/recipe_view.php?id=${n.entity_id}" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">${n.message}</a>`;
                            icon = 'fa-utensils';
                        } else {
                            link = `<span class="${n.is_read ? 'text-gray-700' : 'text-gray-900 font-medium'}">${n.message}</span>`;
                        }

                        notificationItem.innerHTML = `
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-1">
                                    <i class="fas ${icon} ${n.is_read ? 'text-gray-400' : 'text-blue-500'} text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm">${link}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <i class="far fa-clock mr-1"></i>
                                        ${new Date(n.created_at).toLocaleString('de-DE', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                                    </div>
                                </div>
                                ${!n.is_read ? '<div class="flex-shrink-0"><div class="w-2 h-2 bg-blue-500 rounded-full"></div></div>' : ''}
                            </div>
                        `;
                        notificationList.appendChild(notificationItem);
                    });
                } catch (error) {
                    console.error('Error fetching notifications:', error);
                    notificationList.innerHTML = `
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <i class="fas fa-exclamation-triangle text-3xl text-red-400 mb-3"></i>
                            <p class="text-red-500 font-medium">Fehler beim Laden der Benachrichtigungen</p>
                            <p class="text-gray-500 text-sm mt-1">Bitte versuche es später erneut.</p>
                        </div>
                    `;
                }
            }

            // Load notifications when popover is shown
            if (notificationPopover) {
                // Listen for popover toggle events
                notificationPopover.addEventListener('toggle', (e) => {
                    if (e.newState === 'open') {
                        fetchNotifications();
                    }
                });
            }

            // Mark all as read
            if (markAllReadButton) {
                markAllReadButton.addEventListener('click', async () => {
                    if (confirm('Wirklich alle Benachrichtigungen als gelesen markieren?')) {
                        try {
                            const response = await fetch('/api/mark_all_notifications_read.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                                })
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }

                            const result = await response.json();
                            if (result.ok) {
                                await fetchNotifications();
                                // Remove notification badge
                                if (notificationBadge) {
                                    notificationBadge.remove();
                                }
                                // Show success message
                                showNotificationMessage('Alle Benachrichtigungen wurden als gelesen markiert.', 'success');
                            } else {
                                showNotificationMessage('Fehler beim Markieren als gelesen: ' + result.error, 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showNotificationMessage('Netzwerkfehler beim Markieren als gelesen.', 'error');
                        }
                    }
                });
            }

            // Delete all notifications
            if (deleteAllButton) {
                deleteAllButton.addEventListener('click', async () => {
                    if (confirm('Wirklich ALLE Benachrichtigungen unwiderruflich löschen?\n\nDiese Aktion kann nicht rückgängig gemacht werden!')) {
                        try {
                            const response = await fetch('/api/delete_all_notifications.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
                                })
                            });

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }

                            const result = await response.json();
                            if (result.ok) {
                                await fetchNotifications();
                                // Remove notification badge
                                if (notificationBadge) {
                                    notificationBadge.remove();
                                }
                                showNotificationMessage('Alle Benachrichtigungen wurden erfolgreich gelöscht.', 'success');
                            } else {
                                showNotificationMessage('Fehler beim Löschen der Benachrichtigungen: ' + result.error, 'error');
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            showNotificationMessage('Netzwerkfehler beim Löschen der Benachrichtigungen.', 'error');
                        }
                    }
                });
            }

            // Helper function to show notification messages
            function showNotificationMessage(message, type = 'info') {
                // Create a temporary toast notification
                const toast = document.createElement('div');
                toast.className = `fixed top-20 right-4 z-[9999] px-4 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full ${type === 'success' ? 'bg-green-500 text-white' :
                        type === 'error' ? 'bg-red-500 text-white' :
                            'bg-blue-500 text-white'
                    }`;
                toast.innerHTML = `
                    <div class="flex items-center gap-2">
                        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                `;

                document.body.appendChild(toast);

                // Animate in
                setTimeout(() => {
                    toast.classList.remove('translate-x-full');
                }, 100);

                // Animate out and remove
                setTimeout(() => {
                    toast.classList.add('translate-x-full');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 3000);
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const mobileHeader = document.getElementById('mobile-header');
            let lastScrollTop = 0;
            let isScrolling = false;

            // Show mobile header on page load if screen is ≤1024px
            function checkScreenSize() {
                if (window.innerWidth <= 1024) {
                    mobileHeader.classList.remove('hidden');
                    mobileHeader.classList.add('visible');
                } else {
                    mobileHeader.classList.add('hidden');
                    mobileHeader.classList.remove('visible');
                }
            }

            // Handle scroll behavior
            function handleScroll() {
                if (window.innerWidth > 1024) return; // Only for mobile/tablet

                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                // Prevent scroll handling during rapid scrolling
                if (!isScrolling) {
                    window.requestAnimationFrame(function () {
                        // Hide header when scrolling down, show when scrolling up
                        if (scrollTop > lastScrollTop && scrollTop > 100) {
                            // Scrolling down - hide header
                            mobileHeader.classList.add('hidden');
                            mobileHeader.classList.remove('visible');
                        } else if (scrollTop < lastScrollTop || scrollTop <= 100) {
                            // Scrolling up or near top - show header
                            mobileHeader.classList.remove('hidden');
                            mobileHeader.classList.add('visible');
                        }

                        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop; // For Mobile or negative scrolling
                        isScrolling = false;
                    });
                }
                isScrolling = true;
            }

            // Mobile theme toggle
            const mobileThemeToggle = document.getElementById('mobile-theme-toggle');
            if (mobileThemeToggle) {
                mobileThemeToggle.addEventListener('click', function () {
                    if (window.themeManager) {
                        window.themeManager.toggleTheme();
                    }
                });
            }

            // Mobile magnet toggle
            const mobileMagnetToggle = document.getElementById('mobile-magnet-toggle');
            if (mobileMagnetToggle) {
                // Initialize magnet state from localStorage
                const magnetEnabled = localStorage.getItem('cardMagnetEnabled') !== 'false';
                
                // Set initial rotation immediately without transition
                const icon = mobileMagnetToggle.querySelector('i');
                if (icon) {
                    icon.style.transition = 'none'; // Disable transition for initial setup
                    icon.style.transform = magnetEnabled ? 'rotate(180deg)' : 'rotate(0deg)';
                    // Re-enable transition after a brief delay
                    setTimeout(() => {
                        icon.style.transition = 'transform 0.3s ease-in-out';
                    }, 50);
                }
                
                updateMagnetToggle(magnetEnabled);

                mobileMagnetToggle.addEventListener('click', function () {
                    const currentState = localStorage.getItem('cardMagnetEnabled') !== 'false';
                    const newState = !currentState;
                    localStorage.setItem('cardMagnetEnabled', newState.toString());
                    updateMagnetToggle(newState);

                    // Show feedback
                    showMagnetFeedback(newState);
                });
            }

            function updateMagnetToggle(enabled) {
                const button = document.getElementById('mobile-magnet-toggle');
                if (button) {
                    const icon = button.querySelector('i');
                    if (icon) {
                        // Update button styling
                        if (enabled) {
                            button.style.color = 'var(--rh-primary)';
                            button.style.backgroundColor = 'transparent';
                            button.title = 'Card Magnet: EIN';
                        } else {
                            button.style.color = '#000';
                            button.style.backgroundColor = 'transparent';
                            button.title = 'Card Magnet: AUS';
                        }
                        
                        // Update icon classes (preserve existing transition)
                        icon.className = 'fas fa-magnet text-lg transition-transform duration-300 ease-in-out';
                        
                        // Set rotation
                        icon.style.transform = enabled ? 'rotate(180deg)' : 'rotate(0deg)';
                    } else {
                        console.warn('Magnet toggle icon not found');
                    }
                }
            }

            function showMagnetFeedback(enabled) {
                // Create feedback toast
                const toast = document.createElement('div');
                toast.className = 'fixed top-20 left-1/2 z-50 px-4 py-2 rounded-lg text-white text-sm font-medium transition-all duration-300';
                toast.style.background = enabled ? 'linear-gradient(135deg, var(--rh-primary), #10b981)' : 'linear-gradient(135deg, #6b7280, #9ca3af)';
                toast.innerHTML = `
                    <div class="flex items-center gap-2">
                        <i class="fas fa-magnet"></i>
                        <span>Card Magnet ${enabled ? 'aktiviert' : 'deaktiviert'}</span>
                    </div>
                `;

                document.body.appendChild(toast);

                // Animate in
                setTimeout(() => {
                    toast.style.transform = 'translate(-50%, 0)';
                    toast.style.opacity = '1';
                }, 10);

                // Remove after 2 seconds
                setTimeout(() => {
                    toast.style.transform = 'translate(-50%, -20px)';
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 300);
                }, 2000);
            }

            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function () {
                    // Trigger existing mobile nav
                    const mobileNavBtn = document.getElementById('mobile-nav-btn');
                    if (mobileNavBtn) {
                        mobileNavBtn.click();
                    }
                });
            }

            // Mobile user menu
            const mobileUserMenu = document.getElementById('mobile-user-menu');
            if (mobileUserMenu) {
                mobileUserMenu.addEventListener('click', function () {
                    // Trigger existing user menu
                    const userMenuButton = document.getElementById('userMenuButton');
                    if (userMenuButton) {
                        userMenuButton.click();
                    }
                });
            }

            // Initialize
            checkScreenSize();

            // Event listeners
            window.addEventListener('scroll', handleScroll, {
                passive: true
            });
            window.addEventListener('resize', checkScreenSize);

            // Update mobile theme toggle when theme changes
            if (window.themeManager) {
                const originalUpdateThemeToggle = window.themeManager.updateThemeToggle;
                window.themeManager.updateThemeToggle = function (theme) {
                    originalUpdateThemeToggle.call(this, theme);

                    // Update mobile theme toggle
                    const mobileIcon = mobileThemeToggle?.querySelector('i');
                    if (mobileIcon) {
                        mobileIcon.className = theme === 'dark' ? 'fas fa-sun text-lg' : 'fas fa-moon text-lg';
                    }
                };
            }
        });
    </script>

    <?php include __DIR__ . '/admin_nav.php'; ?>

    <!-- Main Content Container -->
    <main class="min-h-screen w-full lg:px-[50px] px-0 lg:mt-[20px] mt-[80px]">