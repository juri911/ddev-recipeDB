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
function displayNotification($message, $isLogin = true, $timeout = 5000) {
    $zIndex = $isLogin ? 'z-50' : '';
    $notificationId = 'notification_' . uniqid(); // Unique ID for each notification
    
    echo '<div id="' . $notificationId . '" class="fixed inset-x-0 top-4 z-50 flex items-start justify-center ' . $zIndex . '">
        <div class="max-w-sm w-full shadow-lg rounded px-4 py-3 relative bg-green-400 border-l-4 border-green-700 text-white transition-opacity duration-300">
            <div class="p-2">
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
    displayNotification($_GET['message'], false, 4000); // 4 seconds timeout
}
?>
    <?php foreach ($recipes as $r): ?>
        <article class="border rounded-lg">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <img src="<?php echo htmlspecialchars($r['author_avatar_path'] ?? SITE_URL . 'images/default_avatar.png'); ?>"
                        class="h-16 w-16 rounded-full object-cover bg-gray-200" alt="Avatar" />
                    <div>
                        <a class="text-sm font-semibold"
                            href="<?php echo htmlspecialchars(profile_url(['id' => $r['user_id'], 'name' => $r['author_name']])); ?>">
                            <?php echo htmlspecialchars($r['author_name']); ?>
                        </a>
                        <?php if (!empty($r['user_titel'])): ?>
                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($r['user_titel']); ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-gray-500"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php if ($user && $user['id'] === (int) $r['user_id']): ?>
                    <div class="relative">
                        <button type="button" class="p-2 text-gray-600 hover:text-gray-800 cursor-pointer"
                            aria-haspopup="dialog" aria-controls="recipe-actions-<?php echo (int) $r['id']; ?>"
                            onclick="openModal('recipe-actions-<?php echo (int) $r['id']; ?>')">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>

                    <div id="recipe-actions-<?php echo (int) $r['id']; ?>" class="fixed inset-0 z-50 hidden" role="dialog"
                        aria-modal="true">
                        <div class="inset-0 bg-black/50 w-full h-full  items-center justify-center"
                            onclick="closeModal('recipe-actions-<?php echo (int) $r['id']; ?>')">
                            <div class="flex justify-center items-center h-full">
                                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">

                                    <div class="flex justify-between items-center mb-4">
                                        <h3 class="font-semibold">Aktionen</h3>
                                        <button id="close-notifications" class="text-gray-500 hover:text-gray-700">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="space-y-2">
                                        <a class="block w-full px-3 py-2 rounded bg-blue-600 text-white text-center text-sm"
                                            href="/recipe_edit.php?id=<?php echo (int) $r['id']; ?>">Bearbeiten</a>
                                        <form method="post" action="/recipe_delete.php"
                                            onsubmit="return confirm('Rezept wirklich löschen?');">
                                            <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                            <input type="hidden" name="csrf_token"
                                                value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <button
                                                class="w-full px-3 py-2 rounded bg-red-600 text-white text-sm cursor-pointer">Löschen</button>
                                        </form>
                                        <button type="button" class="w-full px-3 py-2 rounded border text-sm cursor-pointer"
                                            onclick="closeModal('recipe-actions-<?php echo (int) $r['id']; ?>')">Abbrechen</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="border-t">
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
                                class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2"
                                data-prev>
                                &#10094;
                            </button>
                            <button type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2"
                                data-next>
                                &#10095;
                            </button>
                            <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 bg-black px-5 py-2 text-lg"
                                data-dots></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-400">Kein Bild</div>
                <?php endif; ?>
            </div>

            <div class="p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1">
                    <button id="like-btn-<?php echo (int) $r['id']; ?>"
                    onclick="likeRecipe(<?php echo (int) $r['id']; ?>)"
                    class="like-btn text-2xl <?php echo $user ? '' : 'opacity-60 cursor-not-allowed'; ?> 
                    <?php echo $user && is_liked((int) $r['id'], (int) $user['id']) ? 'text-red-500' : 'text-gray-400'; ?>">
                    <i id="like-heart"
                        class="icon-transition <?php echo $user && is_liked((int) $r['id'], (int) $user['id']) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        <div id="like-count-wrapper-<?php echo (int) $r['id']; ?>"
                            class="text-[16px]<?php echo ((int) $r['likes_count'] === 0 ? ' hidden' : ''); ?>">
                            <span
                                id="like-count-<?php echo (int) $r['id']; ?>"><?php echo (int) $r['likes_count']; ?></span>
                        </div>
                    </div>
                    <?php if ($user): ?>
                        <button id="favorite-btn-<?php echo (int) $r['id']; ?>"
                            onclick="toggleFavorite(<?php echo (int) $r['id']; ?>)"
                            class="favorite-btn text-xl <?php echo is_favorited((int) $r['id'], (int) $user['id']) ? 'text-yellow-500' : 'text-gray-400'; ?>">
                            <i id="like-bookmark"
                                class="icon-transition <?php echo is_favorited((int) $r['id'], (int) $user['id']) ? 'fas' : 'far'; ?> fa-bookmark"></i>
                        </button>
                    <?php endif; ?>
                    <div class="ml-auto text-sm text-gray-600 flex items-center gap-3 lg:flex  hidden">
                        <span><i class="fas fa-cog mr-1"></i>Schwierigkeit: <strong><?php
                        echo match ($r['difficulty']) {
                            'easy' => 'Leicht',
                            'medium' => 'Mittel',
                            'hard' => 'Schwer',
                            default => htmlspecialchars($r['difficulty'])
                        };
                        ?></strong></span>
                        <span><i class="fas fa-clock mr-1"></i>Dauer: <strong>
                                <?php
                                $duration_minutes = (int) $r['duration_minutes']; // Get the duration in minutes
                                $hours = floor($duration_minutes / 60); // Calculate hours
                                $minutes = $duration_minutes % 60; // Calculate remaining minutes
                            
                                echo sprintf("%d:%02d", $hours, $minutes); // Format as h:m (e.g., 2:05)
                                ?>
                                (Stunden/Minunten)
                            </strong></span>
                        <?php if (!empty($r['portions'])): ?>
                            <span><i class="fas fa-users mr-1"></i>Portionen:
                                <strong><?php echo (int) $r['portions']; ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($r['category'])): ?>
                            <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($r['category']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <h2 class="text-lg font-semibold"><a
                        href="<?php echo htmlspecialchars(recipe_url($r)); ?>"><?php echo htmlspecialchars($r['title']); ?></a>
                </h2>
                <p class="text-sm leading-6 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($r['description'])); ?>
                </p>
            </div>
        </article>
    <?php endforeach; ?>
</div>

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
     function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        document.documentElement.classList.add('overflow-hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
        document.documentElement.classList.remove('overflow-hidden');
        document.body.classList.remove('overflow-hidden');
    }
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
            likeBtn.classList.remove('text-gray-400');
            likeBtn.classList.add('text-red-500');
            likeIcon.classList.remove('far');
            likeIcon.classList.add('fas');
        } else {
            likeBtn.classList.remove('text-red-500');
            likeBtn.classList.add('text-gray-400');
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
            <div class="lg:max-w-xl max-w-sm w-full shadow-lg rounded px-4 py-3 bg-[#2196F3] border-l-4 border-[#0e457c] text-white">
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
                    favoriteBtn.classList.remove('text-gray-400');
                    favoriteBtn.classList.add('text-yellow-500');
                    favoriteIcon.classList.remove('far');
                    favoriteIcon.classList.add('fas');
                } else {
                    favoriteBtn.classList.remove('text-yellow-500');
                    favoriteBtn.classList.add('text-gray-400');
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
                dot.className = `h-3 w-3 rounded-full cursor-pointer ${i === currentIndex ? 'bg-white' : 'bg-white/50'}`;
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
        }, { passive: true });

        track.addEventListener('touchmove', (e) => {
            if (!isTouching) return;
            const dx = e.touches[0].clientX - startX;
            currentX = e.touches[0].clientX;
            const percent = (dx / getWidth()) * 100;
            track.style.transform = `translateX(calc(-${currentIndex * 100}% + ${percent}%))`;
            if (Math.abs(dx) > 5) moved = true;
        }, { passive: true });

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
            }, { rootMargin: '400px 0px' });
            observer.observe(sentinel);
        }
    });
</script>

<?php
// Include global footer
include __DIR__ . '/includes/footer.php';
?>