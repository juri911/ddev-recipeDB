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
if (!$recipe) {
    header('Location: /');
    exit;
}

$recipeAuthor = get_user_by_id((int)$recipe['user_id']);

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
<section id="recipe-article" class="min-h-screen w-full md:px-[50px] px-[10px] mt-[50px]">
    <!-- Autor Info -->
    <div class="flex flex-col md:flex-row gap-6 items-center mb-12">
        <div>
            <img src="<?php echo htmlspecialchars($recipe['author_avatar_path'] ? '/' . ltrim($recipe['author_avatar_path'], '/') : SITE_URL . 'images/default_avatar.png'); ?>"
                class="w-36 h-36 rounded-full overflow-hidden  border-4 border-[#2d7ef7] hover:scale-150 transition duration-300" alt="Avatar" />
        </div>
        <div class="text-center md:text-left">
            <h2>
                <a class="text-2xl font-bold mb-2" href="<?php echo htmlspecialchars(profile_url(['id' => $recipe['user_id'], 'name' => $recipe['author_name']])); ?>">
                    <?php echo htmlspecialchars($recipe['author_name']); ?>
                </a>
            </h2>
            <p class="text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($recipe['user_titel']); ?></p>
            <!-- Social links -->
            <?php if ($recipeAuthor && (!empty($recipeAuthor['blog_url']) || !empty($recipeAuthor['website_url']) || !empty($recipeAuthor['instagram_url']) || !empty($recipeAuthor['twitter_url']) || !empty($recipeAuthor['facebook_url']) || !empty($recipeAuthor['tiktok_url']) || !empty($recipeAuthor['youtube_url']))): ?>
                <div class="flex gap-4 items-center justify-center md:justify-start mt-6">
                    <?php if (!empty($recipeAuthor['blog_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['blog_url']); ?>" target="_blank" rel="noopener" title="Blog">
                            <i class="social-icon fas fa-blog fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['website_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['website_url']); ?>" target="_blank" rel="noopener" title="Website">
                            <i class="social-icon fas fa-globe fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['instagram_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['instagram_url']); ?>" target="_blank" rel="noopener" title="Instagram">
                            <i class="social-icon fab fa-instagram fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['twitter_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['twitter_url']); ?>" target="_blank" rel="noopener" title="Twitter/X">
                            <i class="social-icon fab fa-twitter fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['facebook_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['facebook_url']); ?>" target="_blank" rel="noopener" title="Facebook">
                            <i class="social-icon fab fa-facebook fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['tiktok_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['tiktok_url']); ?>" target="_blank" rel="noopener" title="TikTok">
                            <i class="social-icon fab fa-tiktok fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($recipeAuthor['youtube_url'])): ?>
                        <a class="text-gray-600 dark:text-gray-400 hover:text-[#2d7ef7] transition-colors duration-200" href="<?php echo htmlspecialchars($recipeAuthor['youtube_url']); ?>" target="_blank" rel="noopener" title="YouTube">
                            <i class="social-icon fab fa-youtube fa-2xl"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Rezept Header -->
    <div class="mb-12">
        <!-- PDF/Share Buttons und like-->
        <div class="grid grid-cols-2 mb-10">
                  <div class="flex items-center justify-start gap-x-2">
                <!-- PDF Button mit Popover -->
                <div class="relative">
                    <button type="button"
                        id="pdf-btn"
                        onclick="togglePdfPopover()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 font-medium group"
                        title="Als PDF herunterladen"
                        aria-label="Als PDF herunterladen">
                        <i class="fas fa-file-pdf text-lg group-hover:scale-110 transition-transform duration-200"></i>
                        <span class="hidden sm:inline text-sm">PDF</span>
                        <i class="fas fa-chevron-down text-xs ml-1 transition-transform duration-200" id="pdf-arrow"></i>
                    </button>

                    <!-- Popover -->
                     <div id="pdf-popover"
                        class="absolute top-full mt-2 w-56 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 overflow-hidden opacity-0 invisible transform scale-95 transition-all duration-200 z-50">
                        <div class="p-2 space-y-1">
                            <a href="/recipe_pdf.php?id=<?php echo (int)$recipe['id']; ?>&images=1"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-3 px-4 py-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-lg flex items-center justify-center shadow-sm">
                                    <i class="fas fa-images text-white text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Mit Bildern</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Vollständiges PDF</div>
                                </div>
                                <i class="fas fa-download text-gray-400 group-hover:text-emerald-600 transition-colors"></i>
                            </a>

                            <a href="/recipe_pdf.php?id=<?php echo (int)$recipe['id']; ?>&images=0"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-3 px-4 py-3 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-150 group">
                                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-gray-500 to-gray-600 rounded-lg flex items-center justify-center shadow-sm">
                                    <i class="fas fa-file-alt text-white text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">Ohne Bilder</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Nur Text</div>
                                </div>
                                <i class="fas fa-download text-gray-400 group-hover:text-gray-600 transition-colors"></i>
                            </a>
                        </div>
                    </div>
                    
                </div>

                <!-- Share Button -->
                <button type="button"
                    onclick="shareRecipe()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-[#2d7ef7] to-[#1e5fd9] hover:from-[#1e5fd9] hover:to-[#0d4ab8] text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 font-medium group"
                    title="Rezept teilen"
                    aria-label="Rezept teilen">
                    <i class="fas fa-share-alt text-lg group-hover:scale-110 transition-transform duration-200"></i>
                    <span class="hidden sm:inline text-sm">Teilen</span>
                </button>
            </div>
                       <div class="flex items-center justify-end">
                <?php if ($user): ?>
                    <div class="flex items-center gap-3">
                        <!-- User Avatare die geliked haben -->
                        <div id="liked-avatars-<?php echo (int)$recipe['id']; ?>" class="flex items-center -space-x-2 hover:-space-x-1 transition-all duration-300">
                            <?php
                            $likedUsers = get_users_who_liked($recipe['id'], 5);
                            foreach ($likedUsers as $index => $likedUser):
                            ?>
                                <div class="relative group avatar-item">
                                    <img src="<?php echo htmlspecialchars($likedUser['avatar_path'] ? '/' . ltrim($likedUser['avatar_path'], '/') : '/images/default_avatar.png'); ?>"
                                        alt="<?php echo htmlspecialchars($likedUser['name']); ?>"
                                        class="w-10 h-10 rounded-full outline-2 outline-offset-2 outline-[#2d7ef7] bg-white object-cover transition-transform duration-200 group-hover:scale-150 group-hover:z-10 cursor-pointer shadow-sm"
                                        title="<?php echo htmlspecialchars($likedUser['name']); ?>">
                                    <!-- Tooltip -->
                                    <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none z-20">
                                        <?php echo htmlspecialchars($likedUser['name']); ?>
                                        <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ((int)$recipe['likes_count'] > 5): ?>
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-700 dark:to-gray-800 border-2 border-white dark:border-gray-800 flex items-center justify-center shadow-sm more-count">
                                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                        +<?php echo (int)$recipe['likes_count'] - 5; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
 <!-- Like Button -->
                        <button id="like-btn-<?php echo (int)$recipe['id']; ?>"
                            onclick="likeRecipe(<?php echo (int)$recipe['id']; ?>)"
                            class="like-btn text-2xl <?php echo is_liked((int)$recipe['id'], (int)$user['id']) ? 'text-red-500' : 'text-white'; ?>">
                            <i id="like-heart" class="icon-transition <?php echo is_liked((int)$recipe['id'], (int)$user['id']) ? 'fas' : 'far'; ?> fa-solid fa-heart"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rezept Titel -->
        <h1 class="md:text-4xl md:text-left text-center text-3xl font-semibold mb-6"><?php echo htmlspecialchars($recipe['title']); ?></h1>
        <!-- Rezept Beschreibung -->
        <div class="mb-12 md:text-left text-center">
            <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                <?php echo nl2br(htmlspecialchars($recipe['description'])); ?>
            </p>
        </div>

        <div class="flex flex-wrap justify-center md:justify-start gap-6 text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-2">
                <i class="fas fa-clock"></i>
                <span>
                    <?php $duration_minutes = (int) $recipe['duration_minutes']; // Get the duration in minutes
                    $hours = floor($duration_minutes / 60); // Calculate hours
                    $minutes = $duration_minutes % 60; // Calculate remaining minutes

                    // Display with German units
                    if ($hours > 0 && $minutes > 0) {
                        echo $hours . " Std. " . $minutes . " Min.";
                    } elseif ($hours > 0) {
                        echo $hours . " Std.";
                    } else {
                        echo $minutes . " Min.";
                    } ?>
                </span>
            </div>

            <div class="flex items-center gap-2">
                <i class="fas fa-cog"></i>
                <span>
                    <?php $difficulty = htmlspecialchars($recipe['difficulty']);
                    echo match ($difficulty) {
                        'easy' => 'Leicht',
                        'medium' => 'Mittel',
                        'hard' => 'Schwer',
                        default => $difficulty,
                    }; ?>
                </span>
            </div>


            <?php if (!empty($recipe['portions'])): ?>
                <div class="flex items-center gap-2">
                    <i class="fas fa-users"></i>
                    <span>
                        <?php echo (int)$recipe['portions']; ?> Portionen
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($recipe['category'])): ?>
                <div class="flex items-center gap-2">
                    <i class="fas fa-tag"></i>
                    <span>
                        <?php echo htmlspecialchars($recipe['category']); ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="flex items-center gap-2">
                <i class="fas fa-heart"></i>
                <strong id="like-count-<?php echo (int)$recipe['id']; ?>">
                    <?php echo (int)$recipe['likes_count']; ?>
                </strong>
                </span>
            </div>

        </div>
    </div>

    <!-- Rezept Content -->
    <div class="grid lg:grid-cols-2 gap-12">
        <!-- Linke Spalte -->
        <div>
            <?php if (!empty($recipe['images'])): ?>
                <div class="relative" data-slider>
                    <div class="relative rounded-xl overflow-hidden mb-8 border border-gray-200 dark:border-gray-600">
                        <div class="flex h-[400px] transition-transform duration-300" data-track style="transform: translateX(0%);">
                            <?php foreach ($recipe['images'] as $idx => $img): ?>
                                <div class="min-w-full h-full block select-none" data-slide-index="<?php echo $idx; ?>">
                                    <img src="/<?php echo htmlspecialchars($img['file_path']); ?>" class="w-full h-full object-cover" alt="Bild" />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($recipe['images']) > 1): ?>
                        <button type="button" class="absolute left-2 top-1/2 inline-flex justify-center items-center w-8 h-8 rounded-full lg:w-10 lg:h-10 border-2 border-[#2d7ef7] bg-gray-800/30 hover:bg-gray-800/60 focus:ring-4 focus:ring-[#2d7ef7]" data-prev>
                            &#10094;
                        </button>
                        <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex justify-center items-center w-8 h-8 rounded-full lg:w-10 lg:h-10 border-2 border-[#2d7ef7] bg-gray-800/30 hover:bg-gray-800/60 focus:ring-4 focus:ring-[#2d7ef7]" data-next>
                            &#10095;
                        </button>
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5" data-dots></div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="h-[400px] rounded-xl  bg-gray-100 flex items-center justify-center text-gray-400 mb-8 ">Kein Bild</div>
            <?php endif; ?>

            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-2xl font-bold mb-4">Zutaten</h2>
                <ul class="space-y-3">
                    <?php foreach ($recipe['ingredients'] ?? [] as $index => $ingredient): ?>
                        <li class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                id="ingredient-<?php echo $index; ?>"
                                class="ingredient-checkbox h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300 rounded"
                                onchange="toggleIngredient(<?php echo $index; ?>)">
                            <label
                                for="ingredient-<?php echo $index; ?>"
                                id="ingredient-label-<?php echo $index; ?>"
                                class="ingredient-label flex-1 cursor-pointer transition-all duration-200 select-none flex justify-between">
                                <span><?php echo htmlspecialchars($ingredient['name']); ?></span>
                                <span><?php echo htmlspecialchars($ingredient['quantity']) . ' ' . htmlspecialchars($ingredient['unit'] ?? ''); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="mt-4 pt-3 border-t flex justify-between items-center text-sm text-gray-500">
                    <span id="ingredient-progress">0 von <?php echo count($recipe['ingredients'] ?? []); ?> Zutaten bereit</span>
                    <button
                        type="button"
                        onclick="resetIngredients()"
                        class="text-[var(--rh-primary)] hover:text-[var(--rh-primary-hover)]">
                        Alle zurücksetzen
                    </button>
                </div>
            </div>
        </div>
        <!-- Rechte Spalte -->
        <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
            <h2 class="text-2xl font-bold mb-4">Zubereitung</h2>
            <ol class="space-y-6">
                <?php foreach ($recipe['steps'] ?? [] as $index => $step): ?>
                    <li class="flex gap-4">
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-[#2d7ef7] text-white flex items-center justify-center">
                            <?php echo $index + 1; ?>
                        </span>
                        <p><?php echo nl2br(htmlspecialchars($step['description'])); ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </div>
</section>








<section class="border rounded-lg  w-full md:px-[50px] px-[10px] ">
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


</main>

<script>
   // === KOMPLETTER SCRIPT-BEREICH FÜR RECIPE_VIEW.PHP ===

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

// Like functionality mit Avatar-Update
const likeRecipe = async (recipeId) => {
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
        likeCount.textContent = data.likes;

        // Icon und Farbe ändern
        if (data.liked) {
            likeBtn.classList.remove('text-white');
            likeBtn.classList.add('text-red-500');
            likeIcon.classList.remove('far');
            likeIcon.classList.add('fas');

            // Avatar hinzufügen
            addCurrentUserAvatar(recipeId);
        } else {
            likeBtn.classList.remove('text-red-500');
            likeBtn.classList.add('text-white');
            likeIcon.classList.remove('fas');
            likeIcon.classList.add('far');

            // Avatar entfernen
            removeCurrentUserAvatar(recipeId);
        }

        // Animation entfernen
        setTimeout(() => {
            likeIcon.classList.remove('heart-animation');
        }, 300);
    } else if (data.redirect) {
        window.location.href = data.redirect;
    }
};

function addCurrentUserAvatar(recipeId) {
    const avatarsContainer = document.getElementById(`liked-avatars-${recipeId}`);
    if (!avatarsContainer) return;

    // Aktuellen User Avatar und Name aus Meta-Tags holen
    const userAvatar = document.querySelector('meta[name="user-avatar"]')?.content || '/images/default_avatar.png';
    const userName = document.querySelector('meta[name="user-name"]')?.content || 'Du';

    // Neuen Avatar erstellen
    const avatarDiv = document.createElement('div');
    avatarDiv.className = 'relative group avatar-item';
    avatarDiv.style.opacity = '0';
    avatarDiv.style.transform = 'scale(0)';
    avatarDiv.innerHTML = `
        <img src="${userAvatar}" 
             alt="${userName}"
             class="w-10 h-10 outline-2 outline-offset-2 outline-[#2d7ef7] bg-white rounded-full object-cover transition-transform duration-200 group-hover:scale-150 group-hover:z-10 cursor-pointer shadow-sm"
             title="${userName}">
        <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 pointer-events-none z-20">
            ${userName}
            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-gray-900"></div>
        </div>
    `;

    // Am Anfang einfügen
    avatarsContainer.insertBefore(avatarDiv, avatarsContainer.firstChild);

    // Animation
    setTimeout(() => {
        avatarDiv.style.transition = 'all 0.3s ease';
        avatarDiv.style.opacity = '1';
        avatarDiv.style.transform = 'scale(1)';
    }, 10);

    // Wenn mehr als 5 Avatare, letzten entfernen
    const avatars = avatarsContainer.querySelectorAll('.avatar-item');
    if (avatars.length > 5) {
        const lastAvatar = avatars[avatars.length - 1];
        lastAvatar.remove();
    }
}

function removeCurrentUserAvatar(recipeId) {
    const avatarsContainer = document.getElementById(`liked-avatars-${recipeId}`);
    if (!avatarsContainer) return;

    // Ersten Avatar entfernen (der aktuelle User)
    const firstAvatar = avatarsContainer.querySelector('.avatar-item');
    if (firstAvatar) {
        firstAvatar.style.transition = 'all 0.3s ease';
        firstAvatar.style.opacity = '0';
        firstAvatar.style.transform = 'scale(0)';
        setTimeout(() => firstAvatar.remove(), 300);
    }
}

// Instagram-ähnlicher, touch-fähiger Slider
document.addEventListener('DOMContentLoaded', () => {
    const sliders = document.querySelectorAll('[data-slider]');
    sliders.forEach(initSlider);

    // Initialize image zoom after slider
    initImageZoom();
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
            dot.className = `h-3 w-3 rounded-full ${i === currentIndex ? 'bg-[#2d7ef7]' : 'bg-[#2d7ef7]/50'}`;
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

// === ENHANCED IMAGE ZOOM FUNCTIONALITY ===
function initImageZoom() {
    // Create zoom modal HTML structure
    const zoomModal = document.createElement('div');
    zoomModal.id = 'zoom-modal';
    zoomModal.className = 'fixed inset-0 bg-black/90 z-50 hidden items-center justify-center';
    zoomModal.innerHTML = `
        <button id="zoom-close" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 z-10 p-2">
            <i class="fas fa-times"></i>
        </button>
        <div class="absolute top-4 left-4 text-white text-sm z-10">
            <span id="zoom-counter">1 / 1</span>
        </div>
        <div class="relative w-full h-full overflow-hidden">
            <div id="zoom-track" class="flex h-full transition-transform duration-300 ease-out">
                <!-- Zoom images will be inserted here -->
            </div>
            <!-- Navigation arrows for zoom modal -->
            <button id="zoom-prev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300 bg-black/20 rounded-full w-12 h-12 flex items-center justify-center transition-all">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button id="zoom-next" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-3xl hover:text-gray-300 bg-black/20 rounded-full w-12 h-12 flex items-center justify-center transition-all">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-xs opacity-75">
            Pinch to zoom • Drag to pan • Click outside to close
        </div>
    `;
    document.body.appendChild(zoomModal);

    let currentZoomIndex = 0;
    let images = [];
    let zoomLevel = 1;
    let panX = 0;
    let panY = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;
    let startPanX = 0;
    let startPanY = 0;

    // Initialize zoom functionality
    function setupZoom() {
        const slider = document.querySelector('[data-slider]');
        if (!slider) return;

        const slides = slider.querySelectorAll('[data-slide-index]');
        images = Array.from(slides).map(slide => {
            const img = slide.querySelector('img');
            return img ? img.src : null;
        }).filter(Boolean);

        if (images.length === 0) return;

        // Add zoom button to each slide
        slides.forEach((slide, index) => {
            const zoomBtn = document.createElement('button');
            zoomBtn.className = 'absolute top-2 right-2 zoom-btn-overlay text-white rounded-full w-10 h-10 p-2 transition-all opacity-80 hover:opacity-100';
            zoomBtn.innerHTML = '<i class="fas fa-search-plus text-sm"></i>';
            zoomBtn.setAttribute('aria-label', 'Bild vergrößern');
            zoomBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                openZoom(index);
            });
            slide.style.position = 'relative';
            slide.appendChild(zoomBtn);
        });

        // Setup zoom modal content
        setupZoomModal();
    }

    function setupZoomModal() {
        const zoomTrack = document.getElementById('zoom-track');
        const zoomClose = document.getElementById('zoom-close');
        const zoomPrev = document.getElementById('zoom-prev');
        const zoomNext = document.getElementById('zoom-next');
        const modal = document.getElementById('zoom-modal');

        // Create zoom images
        zoomTrack.innerHTML = '';
        images.forEach((src, index) => {
            const slideDiv = document.createElement('div');
            slideDiv.className = 'min-w-full h-full flex items-center justify-center relative overflow-hidden';

            const img = document.createElement('img');
            img.src = src;
            img.className = 'max-w-full max-h-full object-contain cursor-move transition-transform duration-200 ease-out select-none';
            img.style.transform = 'scale(1) translate(0px, 0px)';
            img.draggable = false;

            slideDiv.appendChild(img);
            zoomTrack.appendChild(slideDiv);
        });

        // Event listeners
        zoomClose.addEventListener('click', closeZoom);
        zoomPrev.addEventListener('click', () => navigateZoom(-1));
        zoomNext.addEventListener('click', () => navigateZoom(1));

        // Close on backdrop click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeZoom();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', handleKeydown);

        // Touch and mouse events for zoom and pan
        setupZoomControls();
    }

    function setupZoomControls() {
        const zoomTrack = document.getElementById('zoom-track');
        
        // Mouse wheel zoom
        zoomTrack.addEventListener('wheel', (e) => {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.2 : 0.2;
            adjustZoom(delta, e.clientX, e.clientY);
        }, { passive: false });

        // Touch-Variablen
        let initialDistance = 0;
        let initialZoom = 1;
        let lastTapTime = 0;
        let touchStartTime = 0;
        let lastTouchCenter = { x: 0, y: 0 };
        let swipeStartX = 0;
        let swipeStartY = 0;
        let hasMoved = false;
        let isHorizontalSwipe = false;
        
        // Touch Start Handler
        zoomTrack.addEventListener('touchstart', (e) => {
            touchStartTime = Date.now();
            hasMoved = false;
            isHorizontalSwipe = false;
            
            if (e.touches.length === 2) {
                // === PINCH-TO-ZOOM START ===
                e.preventDefault();
                initialDistance = getDistance(e.touches[0], e.touches[1]);
                initialZoom = zoomLevel;
                
                // Berechne Mittelpunkt zwischen beiden Fingern
                lastTouchCenter = getTouchCenter(e.touches[0], e.touches[1]);
                
            } else if (e.touches.length === 1) {
                const currentTime = Date.now();
                const tapTimeDiff = currentTime - lastTapTime;
                const touch = e.touches[0];
                
                swipeStartX = touch.clientX;
                swipeStartY = touch.clientY;
                
                // === DOUBLE-TAP TO ZOOM ===
                if (tapTimeDiff < 300 && tapTimeDiff > 0) {
                    e.preventDefault();
                    
                    const centerX = touch.clientX;
                    const centerY = touch.clientY;
                    
                    // Toggle zwischen Zoom 1x und 2.5x
                    if (zoomLevel === 1) {
                        setZoom(2.5, centerX, centerY);
                    } else {
                        setZoom(1);
                    }
                    
                    lastTapTime = 0;
                } else {
                    lastTapTime = currentTime;
                    
                    // === PAN/SWIPE START ===
                    if (zoomLevel > 1) {
                        e.preventDefault();
                        startDrag(touch.clientX, touch.clientY);
                    }
                }
            }
        }, { passive: false });

        // Touch Move Handler
        zoomTrack.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2) {
                // === PINCH-TO-ZOOM ===
                e.preventDefault();
                
                const currentDistance = getDistance(e.touches[0], e.touches[1]);
                const currentCenter = getTouchCenter(e.touches[0], e.touches[1]);
                
                // Berechne Zoom-Faktor
                const scale = currentDistance / initialDistance;
                let newZoom = Math.max(1, Math.min(5, initialZoom * scale));
                
                // Sanfte Zoom-Übergänge
                newZoom = Math.round(newZoom * 10) / 10;
                
                // Zoom mit Fokus auf Touch-Mittelpunkt
                setZoom(newZoom, currentCenter.x, currentCenter.y);
                
                // Update Center
                lastTouchCenter = currentCenter;
                
            } else if (e.touches.length === 1) {
                const touch = e.touches[0];
                const deltaX = touch.clientX - swipeStartX;
                const deltaY = touch.clientY - swipeStartY;
                
                if (!hasMoved && (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10)) {
                    hasMoved = true;
                    // Bestimme ob horizontaler oder vertikaler Swipe
                    isHorizontalSwipe = Math.abs(deltaX) > Math.abs(deltaY);
                }
                
                if (zoomLevel === 1 && hasMoved && isHorizontalSwipe) {
                    // === BILD-NAVIGATION (nur wenn nicht gezoomt) ===
                    e.preventDefault();
                    // Visuelles Feedback für Swipe
                    const slideWidth = zoomTrack.parentElement.offsetWidth;
                    const maxSwipe = slideWidth * 0.3; // Max 30% des Bildschirms
                    const constrainedDelta = Math.max(-maxSwipe, Math.min(maxSwipe, deltaX));
                    const currentOffset = -currentZoomIndex * 100;
                    const swipePercent = (constrainedDelta / slideWidth) * 100;
                    zoomTrack.style.transition = 'none';
                    zoomTrack.style.transform = `translateX(${currentOffset + swipePercent}%)`;
                    
                } else if (zoomLevel > 1 && isDragging) {
                    // === PAN (wenn gezoomt) ===
                    e.preventDefault();
                    drag(touch.clientX, touch.clientY);
                }
            }
        }, { passive: false });

        // Touch End Handler
        zoomTrack.addEventListener('touchend', (e) => {
            const touchDuration = Date.now() - touchStartTime;
            
            if (e.touches.length === 0) {
                // Prüfe ob ein Swipe zum nächsten/vorherigen Bild erfolgen soll
                if (zoomLevel === 1 && hasMoved && isHorizontalSwipe) {
                    const touch = e.changedTouches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const slideWidth = zoomTrack.parentElement.offsetWidth;
                    const threshold = slideWidth * 0.2; // 20% für Bildwechsel
                    
                    zoomTrack.style.transition = 'transform 0.3s ease-out';
                    
                    if (deltaX < -threshold && currentZoomIndex < images.length - 1) {
                        // Swipe nach links - nächstes Bild
                        navigateZoom(1);
                    } else if (deltaX > threshold && currentZoomIndex > 0) {
                        // Swipe nach rechts - vorheriges Bild
                        navigateZoom(-1);
                    } else {
                        // Zurück zur aktuellen Position
                        zoomTrack.style.transform = `translateX(-${currentZoomIndex * 100}%)`;
                    }
                }
                
                endDrag();
                
                // Snap zu vernünftigen Zoom-Stufen
                if (touchDuration < 300 && initialDistance > 0) {
                    const zoomSteps = [1, 1.5, 2, 2.5, 3, 4, 5];
                    const closest = zoomSteps.reduce((prev, curr) => 
                        Math.abs(curr - zoomLevel) < Math.abs(prev - zoomLevel) ? curr : prev
                    );
                    
                    if (Math.abs(closest - zoomLevel) < 0.3) {
                        setZoom(closest);
                    }
                }
                
                initialDistance = 0;
            } else if (e.touches.length === 1) {
                // Ein Finger bleibt
                if (zoomLevel > 1) {
                    const touch = e.touches[0];
                    startDrag(touch.clientX, touch.clientY);
                }
            }
        });

        // Touch Cancel Handler
        zoomTrack.addEventListener('touchcancel', () => {
            endDrag();
            initialDistance = 0;
            // Zurück zur aktuellen Position
            const track = document.getElementById('zoom-track');
            if (track) {
                track.style.transition = 'transform 0.3s ease-out';
                track.style.transform = `translateX(-${currentZoomIndex * 100}%)`;
            }
        });

        // === MOUSE EVENTS FÜR DESKTOP ===
        zoomTrack.addEventListener('mousedown', (e) => {
            if (zoomLevel > 1) {
                startDrag(e.clientX, e.clientY);
                e.preventDefault();
            }
        });

        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                drag(e.clientX, e.clientY);
            }
        });

        document.addEventListener('mouseup', endDrag);
        
        // Doppelklick für Desktop
        zoomTrack.addEventListener('dblclick', (e) => {
            e.preventDefault();
            if (zoomLevel === 1) {
                setZoom(2.5, e.clientX, e.clientY);
            } else {
                setZoom(1);
            }
        });
    }

    // === HILFSFUNKTIONEN ===
    
    function getDistance(touch1, touch2) {
        const dx = touch1.clientX - touch2.clientX;
        const dy = touch1.clientY - touch2.clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    function getTouchCenter(touch1, touch2) {
        return {
            x: (touch1.clientX + touch2.clientX) / 2,
            y: (touch1.clientY + touch2.clientY) / 2
        };
    }

    function adjustZoom(delta, centerX, centerY) {
        const newZoom = Math.max(1, Math.min(5, zoomLevel + delta));
        setZoom(newZoom, centerX, centerY);
    }

    function setZoom(newZoom, centerX = null, centerY = null) {
        const oldZoom = zoomLevel;
        zoomLevel = newZoom;

        if (centerX !== null && centerY !== null && oldZoom !== newZoom) {
            // Zoom zum spezifischen Punkt
            const modal = document.getElementById('zoom-modal');
            const rect = modal.getBoundingClientRect();
            
            const centerXRel = (centerX - rect.left) / rect.width - 0.5;
            const centerYRel = (centerY - rect.top) / rect.height - 0.5;

            const zoomRatio = newZoom / oldZoom;
            
            panX = panX * zoomRatio + centerXRel * (oldZoom - newZoom) * 200;
            panY = panY * zoomRatio + centerYRel * (oldZoom - newZoom) * 200;
        }

        // Reset Pan bei 1x Zoom
        if (zoomLevel === 1) {
            panX = 0;
            panY = 0;
        }

        updateTransform();
        updateCursor();
        
        // Haptic Feedback
        if (window.navigator && window.navigator.vibrate) {
            window.navigator.vibrate(10);
        }
    }

    function updateCursor() {
        const currentImg = getCurrentZoomImage();
        if (currentImg) {
            if (zoomLevel > 1) {
                currentImg.style.cursor = isDragging ? 'grabbing' : 'grab';
            } else {
                currentImg.style.cursor = 'zoom-in';
            }
        }
    }

    function startDrag(x, y) {
        isDragging = true;
        startX = x;
        startY = y;
        startPanX = panX;
        startPanY = panY;
        
        const currentImg = getCurrentZoomImage();
        if (currentImg) {
            currentImg.style.cursor = 'grabbing';
        }
    }

    function drag(x, y) {
        if (!isDragging) return;

        const sensitivity = 1.5;
        const dx = (x - startX) * sensitivity;
        const dy = (y - startY) * sensitivity;

        panX = startPanX + dx;
        panY = startPanY + dy;

        // Begrenze Pan
        const maxPan = (zoomLevel - 1) * 150;
        panX = Math.max(-maxPan, Math.min(maxPan, panX));
        panY = Math.max(-maxPan, Math.min(maxPan, panY));

        updateTransform();
    }

    function endDrag() {
        isDragging = false;
        updateCursor();
    }

    function updateTransform() {
        const currentImg = getCurrentZoomImage();
        if (currentImg) {
            currentImg.style.transition = isDragging ? 'none' : 'transform 0.2s ease-out';
            currentImg.style.transform = `scale(${zoomLevel}) translate(${panX}px, ${panY}px)`;
        }
    }

    function getCurrentZoomImage() {
        const zoomTrack = document.getElementById('zoom-track');
        const slides = zoomTrack?.children;
        return slides?.[currentZoomIndex]?.querySelector('img');
    }

    function openZoom(index) {
        currentZoomIndex = index;
        zoomLevel = 1;
        panX = 0;
        panY = 0;

        const modal = document.getElementById('zoom-modal');
        const zoomTrack = document.getElementById('zoom-track');
        const counter = document.getElementById('zoom-counter');
        const prevBtn = document.getElementById('zoom-prev');
        const nextBtn = document.getElementById('zoom-next');

        // Update counter
        counter.textContent = `${index + 1} / ${images.length}`;

        // Show/hide navigation buttons
        prevBtn.style.display = images.length > 1 ? 'flex' : 'none';
        nextBtn.style.display = images.length > 1 ? 'flex' : 'none';

        // Position track
        zoomTrack.style.transform = `translateX(-${index * 100}%)`;

        // Reset all image transforms
        Array.from(zoomTrack.children).forEach(slide => {
            const img = slide.querySelector('img');
            if (img) {
                img.style.transform = 'scale(1) translate(0px, 0px)';
                img.style.cursor = 'zoom-in';
            }
        });

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeZoom() {
        const modal = document.getElementById('zoom-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';

        // Reset zoom state
        zoomLevel = 1;
        panX = 0;
        panY = 0;
    }

    function navigateZoom(direction) {
        const newIndex = currentZoomIndex + direction;
        if (newIndex >= 0 && newIndex < images.length) {
            openZoom(newIndex);
        }
    }

    function handleKeydown(e) {
        const modal = document.getElementById('zoom-modal');
        if (modal.classList.contains('hidden')) return;

        switch (e.key) {
            case 'Escape':
                closeZoom();
                break;
            case 'ArrowLeft':
                navigateZoom(-1);
                break;
            case 'ArrowRight':
                navigateZoom(1);
                break;
            case '+':
            case '=':
                adjustZoom(0.3);
                break;
            case '-':
                adjustZoom(-0.3);
                break;
            case '0':
                setZoom(1);
                break;
        }
        e.preventDefault();
    }

    // Initialize zoom functionality
    setupZoom();
}

// === SHARE FUNCTIONALITY ===
const shareRecipe = async () => {
    const shareData = {
        title: document.querySelector('h1').textContent,
        text: 'Schau dir dieses Rezept an!',
        url: window.location.href
    };
    if (navigator.share) {
        try {
            await navigator.share(shareData);
        } catch (err) {
            // Nutzer hat abgebrochen
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

// === ZUTATEN FUNKTIONALITÄT ===
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

// === PDF POPOVER ===
function togglePdfPopover() {
    const popover = document.getElementById('pdf-popover');
    const arrow = document.getElementById('pdf-arrow');
    const btn = document.getElementById('pdf-btn');

    if (popover.classList.contains('opacity-0')) {
        positionPopover(btn, popover);
        popover.classList.remove('opacity-0', 'invisible', 'scale-95');
        popover.classList.add('opacity-100', 'visible', 'scale-100');
        arrow.classList.add('rotate-180');
    } else {
        popover.classList.add('opacity-0', 'invisible', 'scale-95');
        popover.classList.remove('opacity-100', 'visible', 'scale-100');
        arrow.classList.remove('rotate-180');
    }
}

function positionPopover(btn, popover) {
    const btnRect = btn.getBoundingClientRect();
    const popoverWidth = 224;
    const viewportWidth = window.innerWidth;
    const spaceRight = viewportWidth - btnRect.right;
    const spaceLeft = btnRect.left;

    popover.classList.remove('right-0', 'left-0');

    if (spaceRight >= popoverWidth) {
        popover.classList.add('left-0');
    } else if (spaceLeft >= popoverWidth) {
        popover.classList.add('right-0');
    } else {
        popover.classList.add('right-0');
    }
}

// Schließen beim Klick außerhalb
document.addEventListener('click', function(event) {
    const popover = document.getElementById('pdf-popover');
    const btn = document.getElementById('pdf-btn');

    if (popover && btn && !popover.contains(event.target) && !btn.contains(event.target)) {
        popover.classList.add('opacity-0', 'invisible', 'scale-95');
        popover.classList.remove('opacity-100', 'visible', 'scale-100');
        document.getElementById('pdf-arrow')?.classList.remove('rotate-180');
    }
});

// Neupositionierung bei Resize
window.addEventListener('resize', function() {
    const popover = document.getElementById('pdf-popover');
    const btn = document.getElementById('pdf-btn');

    if (popover && btn && !popover.classList.contains('opacity-0')) {
        positionPopover(btn, popover);
    }
});

// Initial progress update
document.addEventListener('DOMContentLoaded', () => {
    updateIngredientProgress();
});
</script>
<?php
// Include global footer
include __DIR__ . '/includes/footer.php';
?>