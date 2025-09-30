<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../config.php';


$user = current_user();


$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$total = count_feed_recipes($q !== '' ? $q : null);
$recipes = get_feed_recipes($perPage, $offset, $q !== '' ? $q : null);
$totalPages = max(1, (int) ceil($total / $perPage));
csrf_start();


// Set page title and CSRF token for header
$pageTitle = APP_NAME;
$csrfToken = csrf_token();




// Include global header
include __DIR__ . '/includes/header.php';
?>

<?php
// Function to display notification messages with auto-close timeout
function displayNotification($message, $isLogin = true, $timeout = 5000)
{
    $zIndex = $isLogin ? 'z-50' : '';
    $notificationId = 'notification_' . uniqid(); // Unique ID for each notification

    echo '<div id="' . $notificationId . '" class="fixed inset-x-0 top-4 z-50 flex items-start justify-center ' . $zIndex . '">
        <div class="lg:max-w-xl w-[80%] shadow-lg rounded px-4 py-3 relative bg-green-400 border-l-4 border-green-700 text-white transition-opacity duration-300">
            <div class="p-2 my-auto">
                <div class="flex items-start">
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm leading-5 font-medium">' . htmlspecialchars($message) . '</p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button class="inline-flex text-white transition ease-in-out duration-150 hover:text-gray-200"
                                onclick="closeNotification(\'' . $notificationId . '\')">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div class="w-full h-[4px] bg-stone-200 rounded">
                <div class="prog-status"></div>
            </div>
            <style>
            .prog-status {
  height: 4px;
  width: 0%;
  border-radius: 20px;
  background: red;
  animation: 5s linear load infinite;
}
@keyframes load {
  50% {
    width: 50%;
    background: oklch(70.7% 0.165 254.624);
  }
  100% {
    width: 100%;
    background: oklch(79.2% 0.209 151.711);
  }
}

            </style>
        </div>
    </div>
    
    <script>
        // Auto-close notification after specified timeout
        setTimeout(function() {
            closeNotification("' . $notificationId . '");
        }, ' . $timeout . ');
        
        // Function to close notification with fade effect
        function closeNotification(notificationId) {
            const notification = document.getElementById(notificationId);
            if (notification) {
                notification.style.opacity = "0";
                setTimeout(function() {
                    notification.remove();
                }, 300); // Wait for fade transition to complete
            }
        }
    </script>';
}

// Display login message
if (isset($_GET['message'])) {
    displayNotification($_GET['message'], true, 5000); // 5 seconds timeout
}

// Display logout message
if (isset($_GET['message'])) {
    displayNotification($_GET['message'], false, 5000); // 4 seconds timeout
}
?>


<!-- card wrapper -->
<section class="container-fluid mx-auto mt-5">
    <div class="grid md:grid-cols-2 grid-cols-1 gap-6 max-w-6xl mx-auto">
        <?php foreach ($recipes as $r): ?>
            <!-- Card 1 -->
            <div class="border-b-[2px] border-black overflow-hidden shadow-xl/30">
                <!-- Card Header -->
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center">
                        <div
                            class="h-10 w-10 rounded-full overflow-hidden outline-2 outline-offset-2 outline-[#2d7ef7] hover:scale-125 transition duration-300">
                            <img src="<?php echo htmlspecialchars($r['author_avatar_path'] ?? SITE_URL . 'images/default_avatar.png'); ?>"
                                class="h-full w-full object-cover" alt="Avatar" />
                        </div>
                        <div class="ml-3">
                            <a class="font-semibold text-[#2d7ef7] relative after:absolute after:bg-[#2d7ef7] after:h-[2px] after:w-0 after:left-1/2 after:-translate-x-1/2 after:bottom-0 hover:after:w-full after:transition-all after:duration-300"
                                href="<?php echo htmlspecialchars(profile_url(['id' => $r['user_id'], 'name' => $r['author_name']])); ?>">
                                <?php echo htmlspecialchars($r['author_name']); ?>
                            </a>
                            <?php if (!empty($r['user_titel'])): ?>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($r['user_titel']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($user && $user['id'] !== (int) $r['user_id']): ?>
                        <!-- Follow Button -->
                        <button data-user-id="<?php echo (int) $r['user_id']; ?>"
                            onclick="toggleFollow(<?php echo (int) $r['user_id']; ?>)"
                            class="follow-btn px-3 py-1 rounded text-sm font-medium transition-colors duration-200
                <?php echo is_following((int) $user['id'], (int) $r['user_id'])
                            ? 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                            : 'bg-[#2d7ef7] text-white hover:bg-blue-600'; ?>">
                            <span class="follow-text">
                                <?php echo is_following((int) $user['id'], (int) $r['user_id']) ? 'Entfolgen' : 'Folgen'; ?>
                            </span>
                        </button>
                    <?php endif; ?>

                        <?php if ($user && $user['id'] !== (int) $r['user_id']): ?>
                        <!-- Follow Button -->
                        <button data-user-id="<?php echo (int) $r['user_id']; ?>"
                            onclick="toggleFollow(<?php echo (int) $r['user_id']; ?>)"
                            class="follow-btn px-3 py-1 rounded text-sm font-medium transition-colors duration-200
                <?php echo is_following((int) $user['id'], (int) $r['user_id'])
                            ? 'bg-gray-200 text-gray-800 hover:bg-gray-300'
                            : 'bg-[#2d7ef7] text-white hover:bg-blue-600'; ?>">
                            <span class="follow-text">
                                <?php echo is_following((int) $user['id'], (int) $r['user_id']) ? 'Entfolgen' : 'Folgen'; ?>
                            </span>
                        </button>
                    <?php endif; ?>

                    <?php if ($user && $user['id'] === (int) $r['user_id']): ?>
                        <div class="ml-auto">
                            <button type="button"
                                class="p-2 text-[var(--rh-primary)] hover:text-[var(--rh-text)] cursor-pointer"
                                popovertarget="recipe-actions-<?php echo (int) $r['id']; ?>"
                                popovertargetaction="toggle">
                                <i class="fas fa-ellipsis-v fa-xl"></i>
                            </button>
                        </div>

                        <!-- Recipe action Popover -->
                        <div popover id="recipe-actions-<?php echo (int) $r['id']; ?>" 
                             class="popover container mx-auto lg:max-w-4xl min-h-[50%] max-h-full z-[99]">
                            <div class="popover-content-wrapper">
                                <header class="popover-header">
                                    <button popovertarget="recipe-actions-<?php echo (int) $r['id']; ?>" 
                                            popovertargetaction="hide" 
                                            class="popover-close-btn"
                                            aria-label="Schließen" 
                                            title="Schließen">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"
                                            fill="currentColor" aria-hidden="true">
                                            <path
                                                d="M11.9997 10.5865L16.9495 5.63672L18.3637 7.05093L13.4139 12.0007L18.3637 16.9504L16.9495 18.3646L11.9997 13.4149L7.04996 18.3646L5.63574 16.9504L10.5855 12.0007L5.63574 7.05093L7.04996 5.63672L11.9997 10.5865Z">
                                            </path>
                                        </svg>
                                    </button>
                                </header>
                                <section class="popover-section sm:px-[2rem] px-4 py-[1.5rem]">
                                    <!-- Action Buttons -->
                                        <div class="grid lg:grid-cols-2 lg:grid-rows-1 grid-cols-1 grid-rows-2 gap-4 pt-4 gap-4">
                                            <a class="block w-full px-4 py-3 rounded bg-blue-600 text-white text-center text-sm font-medium hover:bg-blue-700 transition-colors"
                                                href="/recipe_edit.php?id=<?php echo (int) $r['id']; ?>">
                                                <i class="fas fa-edit mr-2"></i>Bearbeiten
                                            </a>
                                            <form method="post" action="/recipe_delete.php"
                                                onsubmit="return confirm('Rezept wirklich löschen?');">
                                                <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                                <button type="submit"
                                                    class="w-full px-4 py-3 rounded bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors cursor-pointer">
                                                    <i class="fas fa-trash mr-2"></i>Löschen
                                                </button>
                                            </form>
                                        </div>
                                            <button type="button" 
                                                    class="w-full px-4 py-3 rounded border border-gray-300 text-sm font-medium hover:bg-gray-50 transition-colors cursor-pointer mt-4 shadow-[var(--shadow-6)]"
                                                    popovertarget="recipe-actions-<?php echo (int) $r['id']; ?>"
                                                    popovertargetaction="hide">
                                                Abbrechen
                                            </button>
                                </section>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Card Image -->
                <div class="aspect-square relative group">

                    <?php if (!empty($r['images'])): ?>
                        <div class="relative" data-slider>
                            <div class="aspect-square overflow-hidden bg-black">
                                <div class="flex h-full transition-transform duration-300" data-track
                                    style="transform: translateX(0%);">
                                    <?php foreach ($r['images'] as $idx => $img): ?>
                                        <a href="<?php echo htmlspecialchars(recipe_url($r)); ?>"
                                            class="min-w-full h-full block select-none" data-slide-index="<?php echo $idx; ?>">
                                            <img src="/<?php echo htmlspecialchars($img['file_path']); ?>"
                                                class="w-full h-full object-cover" alt="Bild" />
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php if (count($r['images']) > 1): ?>
                                <button type="button"
                                    class="flex absolute top-0 left-0 z-30 justify-center items-center px-4 h-full cursor-pointer group focus:outline-none"
                                    data-prev>
                                    <span
                                        class="inline-flex justify-center items-center w-8 h-8 rounded-full sm:w-10 sm:h-10 border-2 border-[#2d7ef7] bg-white/30 dark:bg-gray-800/30 group-hover:bg-white/50 dark:group-hover:bg-gray-800/60 group-focus:ring-4 group-focus:ring-[#2d7ef7]">
                                        <svg class="w-5 h-5 text-[#2d7ef7] sm:w-6 sm:h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                        </svg>
                                        <span class="hidden">Previous</span>
                                    </span>
                                </button>
                                <button type="button"
                                    class="flex absolute top-0 right-0 z-30 justify-center items-center px-4 h-full cursor-pointer group focus:outline-none"
                                    data-next>
                                    <span
                                        class="inline-flex justify-center items-center w-8 h-8 rounded-full sm:w-10 sm:h-10 border-2 border-[#2d7ef7] bg-white/30 dark:bg-gray-800/30 group-hover:bg-white/50 dark:group-hover:bg-gray-800/60 group-focus:ring-4 group-focus:ring-[#2d7ef7]">
                                        <svg class="w-5 h-5 text-[#2d7ef7] sm:w-6 sm:h-6" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                        <span class="hidden">Next</span>
                                    </span>
                                </button>
                                <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 px-5 py-2 text-lg"
                                    data-dots>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-400">Kein Bild</div>
                    <?php endif; ?>

                </div>

                <!-- Card Actions -->
                <div class="p-4">
                    <div class="flex items-center">

                        <button id="like-btn-<?php echo (int) $r['id']; ?>"
                            onclick="likeRecipe(<?php echo (int) $r['id']; ?>)"
                            class="like-btn pr-1 <?php echo $user ? '' : 'cursor-not-allowed'; ?> 
                    <?php echo $user && is_liked((int) $r['id'], (int) $user['id']) ? 'text-red-600' : 'text-white'; ?>">
                            <i id="like-heart"
                                class="icon-transition  <?php echo $user && is_liked((int) $r['id'], (int) $user['id']) ? 'fas' : 'far'; ?> fa-solid fa-heart fa-xl"></i>
                        </button>
                        <div id="like-count-wrapper-<?php echo (int) $r['id']; ?>"
                            class="text-[16px]<?php echo ((int) $r['likes_count'] === 0 ? ' hidden' : ''); ?>">
                            <span
                                id="like-count-<?php echo (int) $r['id']; ?>"><?php echo (int) $r['likes_count']; ?></span>
                        </div>

                        <?php if ($user): ?>
                            <button id="favorite-btn-<?php echo (int) $r['id']; ?>"
                                onclick="toggleFavorite(<?php echo (int) $r['id']; ?>)"
                                class="favorite-btn ml-auto <?php echo is_favorited((int) $r['id'], (int) $user['id']) ? 'text-[#2d7ef7]' : 'text-white'; ?>">
                                <i id="like-bookmark"
                                    class="icon-transition <?php echo is_favorited((int) $r['id'], (int) $user['id']) ? 'fas' : 'far'; ?> fa-solid fa-bookmark fa-xl"></i>
                            </button>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- Card Content -->
                <div class="px-4 flex flex-col">
                    <div class="flex items-end justify-between text-sm text-gray-500 pb-2 border-b-1 border-gray-100">
                        <span>
                            <i class="fas fa-clock mr-1"></i>Zubereitungszeit:
                            <strong>
                                <?php
                                $duration_minutes = (int) $r['duration_minutes']; // Get the duration in minutes
                                $hours = floor($duration_minutes / 60); // Calculate hours
                                $minutes = $duration_minutes % 60; // Calculate remaining minutes

                                // Display with German units
                                if ($hours > 0 && $minutes > 0) {
                                    echo $hours . " Std. " . $minutes . " Min.";
                                } elseif ($hours > 0) {
                                    echo $hours . " Std.";
                                } else {
                                    echo $minutes . " Min.";
                                }
                                ?>
                            </strong>
                        </span>

                        <span>
                            <i class="fas fa-cog mr-1"></i>Schwierigkeit:
                            <strong>
                                <?php
                                echo match ($r['difficulty']) {
                                    'easy' => 'Leicht',
                                    'medium' => 'Mittel',
                                    'hard' => 'Schwer',
                                    default => htmlspecialchars($r['difficulty'])
                                };
                                ?>
                            </strong>
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <a href="<?php echo htmlspecialchars(recipe_url($r)); ?>">
                            <h3 class="font-bold text-lg my-2">
                                <?php echo htmlspecialchars($r['title']); ?>
                            </h3>
                            <p class="mb-3 lg:line-clamp-3 hidden text-pretty hyphens-all">
                                <?php echo nl2br(htmlspecialchars($r['description'])); ?>
                            </p>
                        </a>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<!-- card wrapper end -->


<div id="feed-sentinel" class="h-8"></div>

<!-- Pagination -->
<div id="pagination" class="flex items-center justify-center gap-2 mt-8">
    <?php if ($page > 1): ?>
        <a class="px-3 py-1 border rounded"
            href="/?<?php echo http_build_query(['q' => $q, 'page' => $page - 1]); ?>">Zurück</a>
    <?php endif; ?>
    <span class="text-sm text-gray-600">Seite <?php echo $page; ?> von <?php echo $totalPages; ?></span>
    <?php if ($page < $totalPages): ?>
        <a class="px-3 py-1 border rounded"
            href="/?<?php echo http_build_query(['q' => $q, 'page' => $page + 1]); ?>">Weiter</a>
    <?php endif; ?>
</div>

<!-- Page specific JavaScript -->
<script>
    const likeRecipe = async (recipeId) => {
        // Prüfen ob User angemeldet ist
        const isLoggedIn = <?php echo $user ? 'true' : 'false'; ?>;

        if (!isLoggedIn) {
            // Nachricht anzeigen, dass Anmeldung erforderlich ist
            showLoginRequiredMessage();
            return;
        }

        const likeBtn = document.querySelector(`#like-btn-${recipeId}`);
        const likeIcon = likeBtn.querySelector('#like-heart');

        // Animation beim Klick
        likeIcon.classList.add('heart-animation');

        const res = await fetch('/like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            },
            body: 'recipe_id=' + encodeURIComponent(recipeId) + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').content)
        });

        const data = await res.json();

        if (data.ok) {
            const likeCount = document.querySelector(`#like-count-${recipeId}`);
            const likeWrapper = document.querySelector(`#like-count-wrapper-${recipeId}`);

            likeCount.textContent = data.likes;

            if (likeWrapper) {
                if (Number(data.likes) > 0)
                    likeWrapper.classList.remove('hidden');
                else
                    likeWrapper.classList.add('hidden');
            }

            // Icon und Farbe ändern
            if (data.liked) {
                likeBtn.classList.remove('text-white');
                likeBtn.classList.add('text-red-600');
                likeIcon.classList.remove('far');
                likeIcon.classList.add('fas');
            } else {
                likeBtn.classList.remove('text-red-600');
                likeBtn.classList.add('text-white');
                likeIcon.classList.remove('fas');
                likeIcon.classList.add('far');
            }

            // Animation entfernen
            setTimeout(() => {
                likeBtn.classList.remove('heart-animation');
            }, 300);
        } else if (data.redirect) {
            window.location.href = data.redirect;
        }
    };

    // Funktion zum Anzeigen der Anmelde-Nachricht
    function showLoginRequiredMessage() {
        // Prüfen ob bereits eine Nachricht angezeigt wird
        if (document.getElementById('login-required-message')) {
            return;
        }

        const messageHtml = `
        <div id="login-required-message" class="fixed inset-x-0 top-4 z-50 flex items-start justify-center">
            <div class="lg:max-w-xl w-[80%] shadow-lg rounded px-4 py-3 bg-[#2196F3] border-l-4 border-[#0e457c] text-white">
                <div class="p-2">
                    <div class="flex items-start">
                        <div class="ml-3 w-0 flex-1 pt-0.5">
                            <p class="text-sm leading-5 font-medium">
                                Bitte melden Sie sich an, um Rezepte zu liken.
                            </p>
                            <div class="mt-2">
                                <a href="/login.php" class="text-sm underline hover:no-underline">
                                    Zur Anmeldung
                                </a>
                            </div>
                        </div>
                        <div class="ml-4 flex-shrink-0 flex">
                            <button class="inline-flex text-white transition ease-in-out duration-150"
                                onclick="removeLoginRequiredMessage()">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                            <div class="w-full h-[4px] bg-stone-200 rounded">
                <div class="prog-status"></div>
            </div>
            <style>
            .prog-status {
  height: 4px;
  width: 0%;
  border-radius: 20px;
  background: red;
  animation: 5s linear load infinite;
}
@keyframes load {
  50% {
    width: 50%;
    background: oklch(70.7% 0.165 254.624);
  }
  100% {
    width: 100%;
    background: #2196F3;
  }
}

            </style>
            </div>
        </div>
    `;

        document.body.insertAdjacentHTML('afterbegin', messageHtml);

        // Nachricht nach 5 Sekunden automatisch entfernen
        setTimeout(removeLoginRequiredMessage, 5000);
    }

    // Funktion zum Entfernen der Anmelde-Nachricht
    function removeLoginRequiredMessage() {
        const message = document.getElementById('login-required-message');
        if (message) {
            message.remove();
        }
    }

    const toggleFavorite = async (recipeId) => {
        const favoriteBtn = document.querySelector(`#favorite-btn-${recipeId}`);
        const favoriteIcon = favoriteBtn.querySelector('#like-bookmark');

        // Animation beim Klick
        favoriteIcon.classList.add('star-animation');

        try {
            const response = await fetch('/api/toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    recipe_id: recipeId,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });
            const result = await response.json();

            if (result.ok) {
                if (result.favorited) {
                    favoriteBtn.classList.remove('text-white');
                    favoriteBtn.classList.add('text-[#2d7ef7]');
                    favoriteIcon.classList.remove('far');
                    favoriteIcon.classList.add('fas');
                } else {
                    favoriteBtn.classList.remove('text-[#2d7ef7]');
                    favoriteBtn.classList.add('text-white');
                    favoriteIcon.classList.remove('fas');
                    favoriteIcon.classList.add('far');
                }
            } else {
                alert('Fehler: ' + result.error);
            }

            // Animation entfernen
            setTimeout(() => {
                favoriteIcon.classList.remove('star-animation');
            }, 300);
        } catch (error) {
            console.error('Error:', error);
            alert('Netzwerkfehler beim Favorisieren.');

            // Animation entfernen bei Fehler
            setTimeout(() => {
                favoriteIcon.classList.remove('star-animation');
            }, 300);
        }
    };
    // Instagram-ähnlicher, touch-fähiger Slider
    document.addEventListener('DOMContentLoaded', () => {
        const sliders = document.querySelectorAll('[data-slider]');
        sliders.forEach(initSlider);
    });

    function initSlider(root) {
        if (root.getAttribute('data-initialized') === '1') return;
        const track = root.querySelector('[data-track]');
        const slides = Array.from(track ? track.children : []);
        const prevBtn = root.querySelector('[data-prev]');
        const nextBtn = root.querySelector('[data-next]');
        const dotsContainer = root.querySelector('[data-dots]');
        const slideCount = slides.length;
        if (!track || slideCount === 0) return;

        let currentIndex = 0;
        let startX = 0;
        let currentX = 0;
        let isTouching = false;
        let moved = false;

        function getWidth() {
            const box = root.querySelector('.aspect-square');
            return box ? box.clientWidth : root.clientWidth || 1;
        }

        function goTo(index) {
            currentIndex = Math.max(0, Math.min(index, slideCount - 1));
            track.style.transform = `translateX(-${currentIndex * 100}%)`;
            updateDots();
        }

        function updateDots() {
            if (!dotsContainer) return;
            dotsContainer.innerHTML = '';
            for (let i = 0; i < slideCount; i++) {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = `h-3 w-3 rounded-full cursor-pointer ${i === currentIndex ? 'bg-[#2d7ef7]' : 'bg-[#2d7ef7]/50'}`;
                dot.addEventListener('click', () => goTo(i));
                dotsContainer.appendChild(dot);
            }
        }

        // Pfeile
        if (prevBtn) prevBtn.addEventListener('click', () => goTo(currentIndex - 1));
        if (nextBtn) nextBtn.addEventListener('click', () => goTo(currentIndex + 1));

        // Touch-Gesten
        track.addEventListener('touchstart', (e) => {
            if (!e.touches || e.touches.length !== 1) return;
            startX = e.touches[0].clientX;
            currentX = startX;
            isTouching = true;
            moved = false;
            track.style.transition = 'none';
        }, {
            passive: true
        });

        track.addEventListener('touchmove', (e) => {
            if (!isTouching) return;
            const dx = e.touches[0].clientX - startX;
            currentX = e.touches[0].clientX;
            const percent = (dx / getWidth()) * 100;
            track.style.transform = `translateX(calc(-${currentIndex * 100}% + ${percent}%))`;
            if (Math.abs(dx) > 5) moved = true;
        }, {
            passive: true
        });

        track.addEventListener('touchend', () => {
            if (!isTouching) return;
            isTouching = false;
            track.style.transition = '';
            const dx = currentX - startX;
            const threshold = getWidth() * 0.15;
            if (Math.abs(dx) > threshold) {
                if (dx < 0 && currentIndex < slideCount - 1) currentIndex++;
                else if (dx > 0 && currentIndex > 0) currentIndex--;
            }
            goTo(currentIndex);
        });

        // Klicks auf Links beim Swipen unterbinden
        slides.forEach((slideEl) => {
            slideEl.addEventListener('click', (e) => {
                if (moved) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });

        // Initial
        goTo(0);
        root.setAttribute('data-initialized', '1');
    }

    // Infinite Scroll
    document.addEventListener('DOMContentLoaded', () => {
        const feedContainer = document.getElementById('feed-container');
        const sentinel = document.getElementById('feed-sentinel');
        const pagination = document.getElementById('pagination');
        if (pagination) pagination.classList.add('hidden');

        let nextPage = <?php echo (int) $page + 1; ?>;
        let hasMore = <?php echo $page < $totalPages ? 'true' : 'false'; ?>;
        let isLoading = false;
        const q = <?php echo json_encode($q, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        async function loadMore() {
            if (!hasMore || isLoading) return;
            isLoading = true;
            try {
                const params = new URLSearchParams();
                params.set('page', String(nextPage));
                if (q) params.set('q', q);
                const res = await fetch(`/api/feed.php?${params.toString()}`);
                if (!res.ok) throw new Error('Network error');
                const data = await res.json();
                if (data && data.html) {
                    // Append HTML safely using a fragment
                    const tmp = document.createElement('div');
                    tmp.innerHTML = data.html;
                    // Initialize sliders before attaching? Need in DOM for sizes, so after attach.
                    while (tmp.firstChild) {
                        feedContainer.appendChild(tmp.firstChild);
                    }
                    // Initialize any new sliders
                    feedContainer.querySelectorAll('[data-slider]').forEach((slider) => initSlider(slider));
                }
                hasMore = Boolean(data && data.hasMore);
                if (data && typeof data.nextPage === 'number') nextPage = data.nextPage;
                if (!hasMore && sentinel) observer.unobserve(sentinel);
            } catch (e) {
                console.error(e);
            } finally {
                isLoading = false;
            }
        }

        if ('IntersectionObserver' in window && sentinel) {
            var observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        loadMore();
                    }
                });
            }, {
                rootMargin: '400px 0px'
            });
            observer.observe(sentinel);
        }
    });

    // Follow/Unfollow functionality
    const toggleFollow = async (profileId) => {
        // Find ALL follow buttons for this user
        const followBtns = document.querySelectorAll(`[data-user-id="${profileId}"].follow-btn`);

        if (followBtns.length === 0) return;

        // Disable all buttons during request
        followBtns.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.6';
        });

        try {
            const response = await fetch('/api/toggle_follow.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    profile_id: profileId,
                    csrf_token: document.querySelector('meta[name="csrf-token"]').content
                })
            });

            const result = await response.json();

            if (result.ok) {
                // Update ALL follow buttons for this user
                followBtns.forEach(followBtn => {
                    const followText = followBtn.querySelector('.follow-text');

                    if (result.following) {
                        // Now following
                        followBtn.classList.remove('bg-[#2d7ef7]', 'text-white', 'hover:bg-blue-600');
                        followBtn.classList.add('bg-gray-200', 'text-gray-800', 'hover:bg-gray-300');
                        followText.textContent = 'Entfolgen';
                    } else {
                        // Not following anymore
                        followBtn.classList.remove('bg-gray-200', 'text-gray-800', 'hover:bg-gray-300');
                        followBtn.classList.add('bg-[#2d7ef7]', 'text-white', 'hover:bg-blue-600');
                        followText.textContent = 'Folgen';
                    }
                });
            } else {
                console.error('Follow toggle failed:', result.error);
                alert('Fehler beim Folgen/Entfolgen: ' + result.error);
            }
        } catch (error) {
            console.error('Network error:', error);
            alert('Netzwerkfehler beim Folgen/Entfolgen.');
        } finally {
            // Re-enable all buttons
            followBtns.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
        }
    };
</script>

<?php
// Include global footer
include __DIR__ . '/includes/footer.php';
?>