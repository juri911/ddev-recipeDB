<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/comments.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

$user = current_user();
$id = (int)($_GET['id'] ?? 0);
$recipe = get_recipe_by_id($id);
if (!$recipe) { header('Location: /'); exit; }

$commentError = '';
csrf_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (!csrf_validate_request()) {
        $commentError = 'Ungültiges CSRF-Token';
    } else {
        try {
            $content = $_POST['content'] ?? '';
            $parentId = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            
            // Wenn es eine Antwort ist, prüfe ob der User der Rezeptautor ist
            if ($parentId !== null && (int)$user['id'] !== (int)$recipe['user_id']) {
                $commentError = 'Nur der Autor des Rezepts kann auf Kommentare antworten';
            } else {
                add_comment($id, (int)$user['id'], $content, $parentId);
                header('Location: ' . recipe_url(['id' => $id, 'title' => (string)($recipe['title'] ?? '')]) . '#comment-' . ($parentId ?? ''));
                exit;
            }
        } catch (Throwable $e) {
            $commentError = 'Kommentar konnte nicht gespeichert werden';
        }
    }
}

$comments = list_comments($id, 50, 0);


// === SEO für diese Seite ===
$keywords = ['Rezepte', 'Kochen', 'Backen', APP_NAME];
if (!empty($recipe['category'])) {
    $keywords[] = $recipe['category'];
}

$seo = [
    'title' => $recipe['title'] . ' | ' . APP_NAME,
    'description' => substr(strip_tags($recipe['description']), 0, 160),
    'keywords' => implode(', ', $keywords),
     'author' => $recipe['author'] ?? $pageAuthor ?? APP_NAME,
    'image' => !empty($recipe['images'][0]['file_path'])
        ? SITE_URL . ltrim($recipe['images'][0]['file_path'], '/')
        : SITE_URL . 'assets/default_og.png',
    'jsonLd' => [
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => $recipe['title'],
        'description' => $recipe['description'],
        'image' => !empty($recipe['images'][0]['file_path'])
            ? SITE_URL . ltrim($recipe['images'][0]['file_path'], '/')
            : SITE_URL . 'assets/default_og.png',
        'author' => [
            '@type' => 'Person',
            'name' => $recipe['author_name'] ?? APP_NAME,
        ],
        'recipeIngredient' => array_map(fn($i) => ($i['quantity'] ?? '') . ' ' . ($i['unit'] ?? '') . ' ' . $i['name'], $recipe['ingredients'] ?? []),
        'recipeInstructions' => array_map(fn($s) => ['@type' => 'HowToStep', 'text' => $s['description']], $recipe['steps'] ?? []),
        'cookTime' => 'PT' . ((int)$recipe['duration_minutes']) . 'M',
        'recipeYield' => $recipe['portions'] ?? 1,
    ]
];


// Set page title and CSRF token for header

$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/includes/header.php';

?>
        <article id="recipe-article" class="border rounded-lg overflow-hidden">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
     <img src="<?php echo htmlspecialchars($recipe['author_avatar_path'] ? '/' . ltrim($recipe['author_avatar_path'], '/') : SITE_URL . 'images/default_avatar.png'); ?>"
     class="h-16 w-16 rounded-full object-cover bg-gray-200" alt="Avatar" />
     <div>
                        <a class="text-sm font-semibold" href="<?php echo htmlspecialchars(profile_url(['id'=>$recipe['user_id'], 'name'=>$recipe['author_name']])); ?>"><?php echo htmlspecialchars($recipe['author_name']); ?></a>
                   
    <div class="text-xs text-gray-400"><?php echo htmlspecialchars($recipe['user_titel']); ?></div>

                        <div class="text-xs text-gray-500"><?php echo date('d.m.Y H:i', strtotime($recipe['created_at'])); ?></div>
                    </div>
                </div>
               <div class="flex items-center gap-2 sm:gap-3">
                    <label class="flex items-center gap-2 text-sm select-none cursor-pointer">
                        <input type="checkbox" id="pdf-images-toggle" class="sr-only peer" checked>
                        <span class="relative inline-flex h-5 w-9 items-center rounded-full bg-gray-300 peer-checked:bg-emerald-600 transition-colors duration-200 ease-in-out focus:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-400">
                            <span class="absolute left-0.5 h-4 w-4 rounded-full bg-white shadow transform transition-transform duration-200 ease-in-out peer-checked:translate-x-4"></span>
                        </span>
                        <span class="hidden sm:inline">Mit Bildern</span>
                          </label>
                    <a id="pdf-download-link" href="/recipe_pdf.php?id=<?php echo (int)$recipe['id']; ?>&images=1" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-2 border rounded-md text-sm hover:bg-gray-50 shadow-sm" title="Als PDF herunterladen" aria-label="Als PDF herunterladen">
                           <i class="fas fa-file-pdf text-red-600"></i>
                       <span class="hidden sm:inline">PDF</span>
                 </a>
                    <button type="button" onclick="shareRecipe()" class="inline-flex items-center gap-2 px-3 py-2 border rounded-md text-sm hover:bg-gray-50 shadow-sm" title="Rezept teilen" aria-label="Rezept teilen">
                          <i class="fas fa-share-alt"></i>
                       <span class="hidden sm:inline">Teilen</span>
                 </button>
                </div>
            </div>
            <div class="border-t">
                <?php if (!empty($recipe['images'])): ?>
                    <div class="relative" data-slider>
                        <div class="aspect-square overflow-hidden bg-black">
                            <div class="flex h-full transition-transform duration-300" data-track style="transform: translateX(0%);">
                                <?php foreach ($recipe['images'] as $idx => $img): ?>
                                    <div class="min-w-full h-full block select-none" data-slide-index="<?php echo $idx; ?>">
                                        <img src="/<?php echo htmlspecialchars($img['file_path']); ?>" class="w-full h-full object-cover" alt="Bild" />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (count($recipe['images']) > 1): ?>
                        <button type="button" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2" data-prev>
                            &#10094;
                        </button>
                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white rounded-full p-2" data-next>
                            &#10095;
                        </button>
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5" data-dots></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-400">Kein Bild</div>
                <?php endif; ?>
            </div>
            <div class="p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600 flex items-center gap-3">
                        <span><i class="fas fa-cog mr-1"></i>Schwierigkeit: <strong><?php
                            $difficulty = htmlspecialchars($recipe['difficulty']);
                            echo match ($difficulty) {
                                'easy' => 'Leicht',
                                'medium' => 'Mittel',
                                'hard' => 'Schwer',
                                default => $difficulty,
                            };
                        ?></strong></span>
                        <span><i class="fas fa-clock mr-1"></i>Dauer: <strong><?php
$duration_minutes = (int)$recipe['duration_minutes']; // Get the duration in minutes
$hours = floor($duration_minutes / 60); // Calculate hours
$minutes = $duration_minutes % 60; // Calculate remaining minutes

echo sprintf("%d:%02d", $hours, $minutes); // Format as h:m (e.g., 2:05)
?>
(Stunden/Minunten)</strong></span>
                        <?php if (!empty($recipe['portions'])): ?>
                            <span><i class="fas fa-users mr-1"></i>Portionen: <strong><?php echo (int)$recipe['portions']; ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($recipe['category'])): ?>
                            <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($recipe['category']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-heart mr-1"></i>Likes: <strong id="like-count-<?php echo (int)$recipe['id']; ?>"><?php echo (int)$recipe['likes_count']; ?></strong></span>
                    </div>
                    <?php if ($user): ?>
                        <button id="like-btn-<?php echo (int)$recipe['id']; ?>" onclick="likeRecipe(<?php echo (int)$recipe['id']; ?>)" class="like-btn text-2xl <?php echo is_liked((int)$recipe['id'], (int)$user['id']) ? 'text-red-500' : 'text-gray-400'; ?>">
                            <i id="like-heart" class="icon-transition <?php echo is_liked((int)$recipe['id'], (int)$user['id']) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <h1 class="text-xl font-semibold"><?php echo htmlspecialchars($recipe['title']); ?></h1>
                <p class="text-sm leading-6 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
            </div>
        </article>

    <section class="mt-6 border rounded-lg p-4">
            <h2 class="font-semibold mb-3">Zutaten</h2>
            <ul class="space-y-2">
                <?php foreach ($recipe['ingredients'] ?? [] as $index => $ingredient): ?>
                    <li class="flex items-center gap-3">
                        <input 
                            type="checkbox" 
                            id="ingredient-<?php echo $index; ?>" 
                            class="ingredient-checkbox h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
                            onchange="toggleIngredient(<?php echo $index; ?>)"
                        >
                        <label 
                            for="ingredient-<?php echo $index; ?>" 
                            id="ingredient-label-<?php echo $index; ?>"
                            class="ingredient-label flex-1 cursor-pointer transition-all duration-200 select-none"
                        >
                            <?php echo htmlspecialchars($ingredient['quantity']) . ' ' . htmlspecialchars($ingredient['unit'] ?? '') . ' ' . htmlspecialchars($ingredient['name']); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="mt-4 pt-3 border-t flex justify-between items-center text-sm text-gray-500">
                <span id="ingredient-progress">0 von <?php echo count($recipe['ingredients'] ?? []); ?> Zutaten bereit</span>
                <button 
                    type="button" 
                    onclick="resetIngredients()" 
                    class="text-emerald-600 hover:text-emerald-800 underline"
                >
                    Alle zurücksetzen
                </button>
            </div>
        </section>

        <section class="mt-6 border rounded-lg p-4">
            <h2 class="font-semibold mb-3">Zubereitung</h2>
            <ol class="list-decimal list-inside space-y-2">
                <?php foreach ($recipe['steps'] ?? [] as $step): ?>
                    <li><?php echo nl2br(htmlspecialchars($step['description'])); ?></li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="mt-6 border rounded-lg p-4">
            <h2 class="font-semibold mb-3">Kommentare</h2>
            <?php if ($user): ?>
                <?php if ($commentError): ?>
                    <div class="text-red-600 text-sm mb-2"><?php echo htmlspecialchars($commentError); ?></div>
                <?php endif; ?>
                <form method="post" class="space-y-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <textarea name="content" rows="3" class="w-full border rounded px-3 py-2" placeholder="Kommentar schreiben..."></textarea>
                    <div class="flex justify-end">
                        <button class="px-4 py-2 bg-emerald-600 text-white rounded">Senden</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-sm">Bitte <a class="text-blue-600" href="/login.php">anmelden</a>, um zu kommentieren.</div>
            <?php endif; ?>
            <div class="mt-4 space-y-4">
                <?php foreach ($comments as $c): ?>
                    <div class="border rounded overflow-hidden" id="comment-<?php echo (int)$c['id']; ?>">
                        <!-- Hauptkommentar -->
                        <div class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <img src="<?php echo !empty($c['author_avatar_path']) ? '/' . ltrim($c['author_avatar_path'], '/') : '/images/default_avatar.png'; ?>" 
                                     class="w-8 h-8 rounded-full object-cover" alt="">
                                <div>
                                    <div class="text-sm">
                                        <span class="font-semibold"><?php echo htmlspecialchars($c['author_name']); ?></span>
                                        <?php if ((int)$c['user_id'] === (int)$recipe['user_id']): ?>
                                            <span class="ml-1 text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">Autor</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xs text-gray-500"><?php echo date('d.m.Y H:i', strtotime($c['created_at'])); ?></div>
                                </div>
                            </div>
                            <div class="mt-2 text-sm whitespace-pre-line"><?php echo nl2br(htmlspecialchars($c['content'])); ?></div>
                            
                            <?php if ($user && (int)$recipe['user_id'] === (int)$user['id']): ?>
                                <div class="mt-2">
                                    <button onclick="toggleReplyForm(<?php echo (int)$c['id']; ?>)" 
                                            class="text-sm text-blue-600 hover:text-blue-800">
                                        Antworten
                                    </button>
                                </div>
                                
                                <!-- Antwortformular (standardmäßig versteckt) -->
                                <form method="post" id="reply-form-<?php echo (int)$c['id']; ?>" 
                                      class="mt-3 hidden" onsubmit="return validateReply(this);">
                                    <input type="hidden" name="csrf_token" 
                                           value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                    <input type="hidden" name="parent_id" 
                                           value="<?php echo (int)$c['id']; ?>">
                                    <textarea name="content" rows="2" 
                                            class="w-full border rounded px-3 py-2 text-sm" 
                                            placeholder="Ihre Antwort..."></textarea>
                                    <div class="flex justify-end gap-2 mt-2">
                                        <button type="button" 
                                                onclick="toggleReplyForm(<?php echo (int)$c['id']; ?>)" 
                                                class="px-3 py-1 text-sm border rounded">
                                            Abbrechen
                                        </button>
                                        <button type="submit" 
                                                class="px-3 py-1 text-sm bg-blue-600 text-white rounded">
                                            Antworten
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Antworten -->
                        <?php if (!empty($c['replies'])): ?>
                            <div class="bg-gray-50 px-4 py-2 space-y-3">
                                <?php foreach ($c['replies'] as $reply): ?>
                                    <div class="flex items-start gap-2" id="comment-<?php echo (int)$reply['id']; ?>">
                                        <img src="<?php echo !empty($reply['author_avatar_path']) ? '/' . ltrim($reply['author_avatar_path'], '/') : '/images/default_avatar.png'; ?>" 
                                             class="w-6 h-6 rounded-full object-cover" alt="">
                                        <div class="flex-1">
                                            <div class="text-sm">
                                                <span class="font-semibold"><?php echo htmlspecialchars($reply['author_name']); ?></span>
                                                <?php if ((int)$reply['user_id'] === (int)$recipe['user_id']): ?>
                                                    <span class="ml-1 text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded">Autor</span>
                                                <?php endif; ?>
                                                <span class="text-xs text-gray-500 ml-1"><?php echo date('d.m.Y H:i', strtotime($reply['created_at'])); ?></span>
                                            </div>
                                            <div class="text-sm whitespace-pre-line"><?php echo nl2br(htmlspecialchars($reply['content'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <script>
        function toggleReplyForm(commentId) {
            const form = document.getElementById(`reply-form-${commentId}`);
            if (form) {
                form.classList.toggle('hidden');
                if (!form.classList.contains('hidden')) {
                    form.querySelector('textarea').focus();
                }
            }
        }
        
        function validateReply(form) {
            const content = form.querySelector('textarea').value.trim();
            if (content === '') {
                alert('Bitte geben Sie eine Antwort ein.');
                return false;
            }
            return true;
        }
        </script>
    </main>

    <script>
        // Like functionality
        const likeRecipe = async (recipeId) => {
            const likeBtn = document.querySelector(`#like-btn-${recipeId}`);
            const likeIcon = likeBtn.querySelector('#like-heart');
            
            // Animation beim Klick
            likeIcon.classList.add('heart-animation');
            
            const res = await fetch('/like.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                body: 'recipe_id=' + encodeURIComponent(recipeId) + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').content)
            });
            const data = await res.json();
            if (data.ok) {
                const likeCount = document.querySelector(`#like-count-${recipeId}`);
                likeCount.textContent = data.likes;
                
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
                    likeIcon.classList.remove('heart-animation');
                }, 300);
            } else if (data.redirect) {
                window.location.href = data.redirect;
            }
        };

        // Instagram-ähnlicher, touch-fähiger Slider (wie auf index.php)
        document.addEventListener('DOMContentLoaded', () => {
            const sliders = document.querySelectorAll('[data-slider]');
            sliders.forEach(initSlider);
        });

        function initSlider(root) {
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
                    dot.className = `h-1.5 w-1.5 rounded-full ${i === currentIndex ? 'bg-white' : 'bg-white/50'}`;
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

            // Klicks auf Elemente beim Swipen unterbinden
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
        }

        // Share
        const shareRecipe = async () => {
            const shareData = {
                title: <?php echo json_encode((string)$recipe['title']); ?>,
                text: 'Schau dir dieses Rezept an!',
                url: window.location.href
            };
            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                } catch (err) {
                    // Nutzer hat abgebrochen oder Fehler – nichts tun
                }
            } else {
                const url = window.location.href;
                try {
                    await navigator.clipboard.writeText(url);
                    alert('Link kopiert: ' + url);
                } catch (e) {
                    const temp = document.createElement('input');
                    temp.value = url;
                    document.body.appendChild(temp);
                    temp.select();
                    document.execCommand('copy');
                    document.body.removeChild(temp);
                    alert('Link kopiert: ' + url);
                }
            }
        };

        // Toggle PDF images option
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('pdf-images-toggle');
            const link = document.getElementById('pdf-download-link');
            if (!toggle || !link) return;
            const baseUrl = '/recipe_pdf.php?id=<?php echo (int)$recipe['id']; ?>';
            const updateHref = () => {
                const withImages = toggle.checked ? '1' : '0';
                link.href = baseUrl + '&images=' + withImages;
            };
            toggle.addEventListener('change', updateHref);
            updateHref();
        });

        // Zutaten-Funktionalität
        function toggleIngredient(index) {
            const checkbox = document.getElementById(`ingredient-${index}`);
            const label = document.getElementById(`ingredient-label-${index}`);
            
            if (checkbox.checked) {
                label.classList.add('line-through', 'text-gray-400', 'opacity-60');
            } else {
                label.classList.remove('line-through', 'text-gray-400', 'opacity-60');
            }
            
            updateIngredientProgress();
        }
        
        function updateIngredientProgress() {
            const checkboxes = document.querySelectorAll('.ingredient-checkbox');
            const checked = document.querySelectorAll('.ingredient-checkbox:checked').length;
            const total = checkboxes.length;
            const progressElement = document.getElementById('ingredient-progress');
            
            if (progressElement) {
                progressElement.textContent = `${checked} von ${total} Zutaten bereit`;
            }
        }
        
        function resetIngredients() {
            const checkboxes = document.querySelectorAll('.ingredient-checkbox');
            const labels = document.querySelectorAll('.ingredient-label');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            labels.forEach(label => {
                label.classList.remove('line-through', 'text-gray-400', 'opacity-60');
            });
            
            updateIngredientProgress();
        }
        
        // Initial progress update
        document.addEventListener('DOMContentLoaded', () => {
            updateIngredientProgress();
        });
    </script>
<?php
// Include global footer
include __DIR__ . '/includes/footer.php';
?>