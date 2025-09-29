<?php
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

// SEO Defaults – können pro Seite überschrieben werden
$seo = [
    'title' => $seo['title'] ?? $pageTitle ?? APP_NAME,
    'description' => $seo['description'] ?? $pageDescription ?? 'Entdecke leckere Rezepte, Inspiration und Food-Tipps auf ' . APP_NAME . '.',
    'keywords' => $seo['keywords'] ?? $pageKeywords ?? 'Rezepte, Kochen, Backen, Essen, Foodblog',
    'author' => $seo['author'] ?? $pageAuthor ?? APP_NAME,
    'url' => $seo['url'] ?? $pageUrl ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]",
    'image' => $seo['image'] ?? $pageImage ?? '/assets/default_og.png',
    'jsonLd' => $seo['jsonLd'] ?? null,
];
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,  initial-scale=1.0">
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
</head>

<body>

    <!-- Top gradient -->
    <div class="w-full h-[6px] bg-gradient-to-r from-[#2d7ef7] to-fuchsia-400 fixed top-0 z-[99999]"></div>

    <!-- Header -->
    <header
        class="flex lg:sticky lg:top-0 fixed bottom-0 left-0 right-0 z-10 container-fluid min-h-[80px] mx-auto justify-between 
               items-center border-b-[1px] bg-[var(--rh-bg)]/40 border-[var(--rh-bg-secondary)] backdrop-blur-lg">
        <!-- Logo -->
        <div class="lg:flex hidden content-start">
            <a href="/"
                class="flex items-center z-[9999] navbar-brand hover:scale-150 origin-left transition duration-300 ease-in-out pl-3">
                <?php //echo get_app_logo_html(); 
                ?>
                <svg fill="currentColor" width="160px" height="50px">
                    <use href="#logo"></use>
                </svg>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="flex items-center justify-between lg:justify-end h-[75px] w-full">
            <div class="flex justify-center lg:justify-end w-full items-center gap-x-0 lg:gap-x-5 text-[16px]">
                <!-- Link group -->
                <div class="flex justify-center items-center">
                    <!-- Home -->
                    <a href="/"
                        class="bg-transparent lg:bg-[#2d7ef7] border-0 lg:border-1 border-[#2d7ef7]  hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent text-white lg:py-1 lg:px-3 p-4  lg:rounded-l transition duration-600 aspect-square lg:aspect-auto rounded-none">
                        <i class="fa-solid fa-house lg:text-base text-2xl"></i>
                    </a>
                    <!-- Search -->
                    <button popovertarget="search-popover"
                        class="bg-transparent lg:bg-[#2d7ef7] border-0 lg:border-1  border-[#2d7ef7] hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent text-white lg:py-1 lg:px-3 p-4 transition duration-600 aspect-square lg:aspect-auto rounded-none">
                        <i class="fas fa-magnifying-glass lg:text-base text-2xl"></i>
                    </button>
                    <!-- Categories -->
                    <a href="/categories.php"
                        class="lg:block hidden bg-[#2d7ef7] border-1 border-[#2d7ef7] hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent text-white lg:py-1 lg:px-3 p-4 lg:rounded-r transition duration-600 aspect-square lg:aspect-auto rounded-none"">
                        <i class=" fa-solid fa-tags lg:text-base text-2xl"></i>
                    </a>
                </div>
                <div class="flex items-center gap-x0 lg:gap-x-2 pr-0 lg:pr-3">
                    <?php if (isset($user) && $user): ?>
                        <!-- New Recipe Button -->
                        <a href="/recipe_new.php"
                            class="bg-transparent lg:bg-[#2d7ef7] border-0 lg:border-1 bg-[#2d7ef7] border-[#2d7ef7] hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent text-white p-4 lg:py-1 lg:px-3 aspect-square lg:aspect-auto rounded-none lg:rounded transition duration-600">
                            <i class="fa-solid fa-feather lg:text-base text-2xl p-0 lg:pr-2"></i>
                            <p class="lg:inline hidden">Neues Rezept</p>
                        </a>
                        <!-- Notifications -->
                        <?php
                        // Ensure function exists before calling
                        $unreadCount = 0;
                        if (function_exists('count_unread_notifications')) {
                            $unreadCount = count_unread_notifications((int) $user['id']);
                        }
                        ?>
                        <button popovertarget="notification-bells" class="bg-transparent lg:bg-[#2d7ef7]  relative text-white lg:text-gray-600 bg-[var(--rh-primary)] lg:bg-transparent hover:text-[var(--rh-text)] lg:p-0 p-4 aspect-square lg:aspect-auto hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent transition duration-600">
                            <i class="fa-solid fa-bell lg:text-[26px] text-2xl"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span
                                    class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
                        </button>

                        <!-- User Dropdown -->
                        <div class="relative inline-block text-left ml-auto">
                            <!-- Avatar Button -->
                            <button id="userMenuButton" class="flex items-center focus:outline-none lg:pr-4 p-4 aspect-square lg:aspect-auto">
                                <div
                                    class="h-10 w-10 rounded-full overflow-hidden outline-2 outline-offset-2 outline-[#2d7ef7] hover:outline-[var(--rh-text)] transition duration-600 cursor-pointer">
                                    <img src="<?php echo htmlspecialchars(isset($user['avatar_path']) && $user['avatar_path'] ? absolute_url_from_path((string) $user['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>"
                                        class="h-10 w-10 rounded-full object-cover" alt="Avatar" />
                                </div>
                                <span
                                    class="ml-2 text-sm font-medium hidden"><?php echo htmlspecialchars($user['name']); ?></span>
                            </button>

                            <!-- Dropdown - Updated classes for flexible positioning -->
                            <div id="userMenuDropdown"
                                class="hidden absolute right-0 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50 transform transition-all duration-200 ease-out">
                                <a href="<?php echo htmlspecialchars(profile_url(['id' => $user['id'], 'name' => $user['name']])); ?>"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-t-lg transition-colors">
                                    <i class="fas fa-user mr-2"></i>Profil ansehen
                                </a>
                                <a href="/profile_edit.php"
                                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                    <i class="fas fa-edit mr-2"></i>Profil bearbeiten
                                </a>
                                <div class="border-t border-gray-200"></div>
                                <a href="/logout.php"
                                    class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100 rounded-b-lg transition-colors">
                                    <i class="fa-solid fa-arrow-right-from-bracket mr-2 rotate-180"></i>Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-x-0 lg:gap-x-5 text-[16px]">
                            <a href="/login.php"
                                class="flex items-center bg-transparent lg:bg-[#2d7ef7] border-0 lg:border-1 border-[#2d7ef7] font-semibold hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent 
                                text-white p-4 lg:py-1 lg:px-3 lg:rounded rounded-none transition duration-600 aspect-square lg:aspect-auto">
                                <i class="fa-solid fa-arrow-right-from-bracket lg:text-base text-2xl pr-0 lg:pr-2"></i>
                                <p class="lg:inline hidden">Login</p>
                            </a>

                            <a class="flex items-center lg:gap-x-1 font-semibold text-white lg:text-[#2d7ef7] relative after:absolute 
                                    after:bg-[#2d7ef7] after:h-[2px] after:w-0 after:left-1/2 after:-translate-x-1/2 after:bottom-0 lg:hover:after:w-full after:transition-all after:duration-300 aspect-square lg:aspect-auto lg:p-0 p-4"
                                href="/register.php">
                                <p class="lg:inline hidden">Registrieren</p><i class="fa-solid fa-pencil lg:text-base text-2xl"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Mobile menu button -->
                <button id="mobile-nav-btn" class="relative flex  items-end justify-center mr-0 lg:mr-3"
                    aria-label="Menü öffnen" aria-expanded="false" aria-controls="mobile-nav-panel">
                    <i
                        class="fa-solid fa-burger text-[36px] text-[var(--rh-primary)] hover:text-[var(--rh-text)]  transition-all duration-300 ease-out aspect-square lg:aspect-auto lg:p-0 p-4"></i>

                </button>
            </div>
        </nav>
    </header>

    <!-- Menu Overlay -->
    <div id="mobile-nav-overlay"
        class="fixed inset-0 bg-black/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-300 z-40">
    </div>

    <!-- Drawer -->
    <nav id="mobile-nav-panel"
        class="fixed top-0 right-0 h-full w-full sm:w-[500px] bg-white text-[var(--rh-text-black)] shadow-2xl z-50 translate-x-full 
                transition-transform duration-300 ease-in-out will-change-transform flex flex-col"
        aria-hidden="true">
        <div class="flex items-center justify-end p-4">
            <button id="mobile-nav-close"
                class=" relative w-[46px] h-[46px] bg-[var(--rh-primary)] rounded-full flex flex-col items-center justify-center gap-1.5 active:scale-95 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                aria-label="Menü schließen" aria-expanded="false" aria-controls="mobile-nav-panel">
                <i class="fa-solid fa-xmark text-[26px]  transition-all duration-300 ease-out"></i>
            </button>
        </div>

        <ul class="flex-1 overflow-y-auto p-4 space-y-3 dark:text-black">
            <li><a href="/" class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Startseite</a></li>
            <li><a href="/categories.php" class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Rezepte</a>
            </li>
            <li><a href="/blog.php" class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Blog</a></li>
            <li><a href="/kontakt.php" class="block px-3 py-2 rounded-lg hover:bg-gray-100 transition">Kontakt</a></li>
        </ul>
    </nav>


    <!-- Notification Overlay -->
    <?php if (isset($user) && $user): ?>
        <div id="notification-overlay" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[var(--rh-text-black)]">Benachrichtigungen</h3>
                    <button id="close-notifications" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="notification-list" class="space-y-2 max-h-64 overflow-y-auto">
                    <!-- Notifications will be loaded here -->
                </div>
                <div class="mt-4 pt-4 border-t flex justify-between items-center">
                    <button id="mark-all-read"
                        class="text-sm text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)]">Alle als gelesen
                        markieren</button>
                    <button id="delete-all-notifications" class="text-sm text-red-600 hover:text-red-700">Alle
                        löschen</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

<!--Notification PopOver -->
<div popover id="notification-bells" class="popover container mx-auto lg:max-w-4xl min-h-[50%] max-h-full">
  <div class="popover-content-wrapper">
    <header class="popover-header">
      <button popovertarget="notification-bells" popovertargetaction="hide" class="popover-close-btn"
        aria-label="Close search" title="Close search">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"
          fill="currentColor" aria-hidden="true">
          <path
            d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z">
          </path>
        </svg>
      </button>
    </header>
    <section class="popover-section sm:px-[2rem] px-1 py-[3.5rem]">
      <!-- PopOver Content -->
      <?php if (isset($user) && $user): ?>

            <div class="bg-white p-6 rounded-lg shadow-xl w-full max-w-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[var(--rh-text-black)]">Benachrichtigungen</h3>
                    
                </div>
                <div id="notification-list" class="space-y-2 max-h-64 overflow-y-auto">
                    <!-- Notifications will be loaded here -->
                </div>
                <div class="mt-4 pt-4 border-t flex justify-between items-center">
                    <button id="mark-all-read"
                        class="text-sm text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)]">Alle als gelesen
                        markieren</button>
                    <button id="delete-all-notifications" class="text-sm text-red-600 hover:text-red-700">Alle
                        löschen</button>
                </div>
            </div>

    <?php endif; ?>
      <!-- PopOver Content end  -->
    </section>
  </div>
</div>



    <!-- Header JavaScript - Loaded immediately -->
    <script>
        // Header-specific JavaScript that needs to run immediately
        document.addEventListener("DOMContentLoaded", function() {
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

            // Diese Funktion ersetzt den ursprünglichen User Dropdown Code in Ihrem header.php
            // Ersetzen Sie den Code ab "// User Dropdown Menu" bis zum Ende des if-Blocks

            // Verbesserte User Dropdown Menu Funktionalität
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
            // Notification System
            const notificationBell = document.getElementById('notification-bells');
            const notificationOverlay = document.getElementById('notification-overlay');
            const notificationList = document.getElementById('notification-list');
            const closeNotifications = document.getElementById('close-notifications');
            const markAllReadButton = document.getElementById('mark-all-read');
            const deleteAllButton = document.getElementById('delete-all-notifications');

            // Fetch notifications function
            async function fetchNotifications() {
                if (!notificationList) return;

                try {
                    const response = await fetch('/api/get_notifications.php', {
                        headers: {
                            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        }
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const notifications = await response.json();

                    notificationList.innerHTML = '';
                    if (notifications.length === 0) {
                        notificationList.innerHTML = '<p class="text-gray-500">Keine Benachrichtigungen.</p>';
                        return;
                    }

                    notifications.forEach(n => {
                        const notificationItem = document.createElement('div');
                        notificationItem.className = `p-3 border rounded ${n.is_read ? 'bg-gray-100' : 'bg-white font-semibold'}`;

                        let link = '';
                        if (n.type === 'new_recipe' && n.entity_id) {
                            link = `<a href="/recipe/${n.entity_id}" class="text-blue-600 hover:underline">${n.message}</a>`;
                        } else {
                            link = n.message;
                        }

                        notificationItem.innerHTML = `
                            <div>${link}</div>
                            <div class="text-xs text-gray-500 mt-1">${new Date(n.created_at).toLocaleString()}</div>
                        `;
                        notificationList.appendChild(notificationItem);
                    });
                } catch (error) {
                    console.error('Error fetching notifications:', error);
                    notificationList.innerHTML = '<p class="text-red-500">Fehler beim Laden der Benachrichtigungen.</p>';
                }
            }

            // Notification bell click handler
            if (notificationBell && notificationOverlay) {
                notificationBell.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Notification bell clicked'); // Debug log
                    notificationOverlay.classList.remove('hidden');
                    notificationOverlay.classList.add('flex');
                    await fetchNotifications();
                });
            }

            // Close notifications
            if (closeNotifications && notificationOverlay) {
                closeNotifications.addEventListener('click', () => {
                    notificationOverlay.classList.add('hidden');
                    notificationOverlay.classList.remove('flex');
                });
            }

            // Close on overlay click
            if (notificationOverlay) {
                notificationOverlay.addEventListener('click', (e) => {
                    if (e.target === notificationOverlay) {
                        notificationOverlay.classList.add('hidden');
                        notificationOverlay.classList.remove('flex');
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
                                const unreadSpan = notificationBell?.querySelector('span');
                                if (unreadSpan) unreadSpan.remove();
                            } else {
                                alert('Fehler beim Markieren als gelesen: ' + result.error);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('Netzwerkfehler beim Markieren als gelesen.');
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
                                const unreadSpan = notificationBell?.querySelector('span');
                                if (unreadSpan) unreadSpan.remove();
                                // Show success message
                                alert('Alle Benachrichtigungen wurden erfolgreich gelöscht.');
                            } else {
                                alert('Fehler beim Löschen der Benachrichtigungen: ' + result.error);
                            }
                        } catch (error) {
                            console.error('Error:', error);
                            alert('Netzwerkfehler beim Löschen der Benachrichtigungen.');
                        }
                    }
                });
            }
        });
    </script>

    <?php include __DIR__ . '/admin_nav.php'; ?>

    <div class="flex content-start w-full flex lg:hidden px-4 py-4">
        <a href="/"
            class="flex items-center navbar-brand hover:scale-150 origin-left transition duration-300 ease-in-out">
            <svg fill="currentColor" width="160px" height="50px">
                <use href="#logo"></use>
            </svg>
        </a>
    </div>
    <!-- Main Content Container -->
    <main class="min-h-screen w-full md:px-[50px] px-[10px] mt-[20px]">