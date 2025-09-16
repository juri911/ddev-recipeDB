<?php
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/recipes.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$page = max(1, (int)($_GET['page'] ?? 1));
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $total = count_feed_recipes($q !== '' ? $q : null);
    $recipes = get_feed_recipes($perPage, $offset, $q !== '' ? $q : null);
    $totalPages = max(1, (int)ceil($total / $perPage));

    ob_start();
    foreach ($recipes as $r): ?>
        <article class="bg-white border rounded-lg">
            <div class="px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <img src="<?php echo htmlspecialchars($r['author_avatar_path'] ?? SITE_URL . 'images/default_avatar.png'); ?>" class="h-16 w-16 rounded-full object-cover bg-gray-200" alt="Avatar" />
                    <div>
                        <a class="text-sm font-semibold" href="<?php echo htmlspecialchars(profile_url(['id' => $r['user_id'], 'name' => $r['author_name']])); ?>"><?php echo htmlspecialchars($r['author_name']); ?></a>
                        <div class="text-xs text-gray-500"><?php echo date('d.m.Y H:i', strtotime($r['created_at'])); ?></div>
                    </div>
                </div>
                <?php $user = current_user(); if ($user && $user['id'] === (int)$r['user_id']): ?>
                    <div class="flex items-center gap-2 text-sm">
                        <a class="text-blue-600" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Bearbeiten</a>
                        <form method="post" action="/recipe_delete.php" onsubmit="return confirm('Rezept wirklich löschen?');">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <button class="text-red-600">Löschen</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="border-t">
                <?php if (!empty($r['images'])): ?>
                    <div class="relative" data-slider>
                        <div class="aspect-square overflow-hidden bg-black">
                            <div class="flex h-full transition-transform duration-300" data-track style="transform: translateX(0%);">
                                <?php foreach ($r['images'] as $idx => $img): ?>
                                    <a href="<?php echo htmlspecialchars(recipe_url($r)); ?>" class="min-w-full h-full block select-none" data-slide-index="<?php echo $idx; ?>">
                                        <img src="/<?php echo htmlspecialchars($img['file_path']); ?>" class="w-full h-full object-cover" alt="Bild" />
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if (count($r['images']) > 1): ?>
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
                <div class="flex items-center gap-2">
                    <div class="flex items-center gap-1"> 
                    <button id="like-btn-<?php echo (int)$r['id']; ?>" onclick="likeRecipe(<?php echo (int)$r['id']; ?>)" class="like-btn text-2xl <?php echo $user ? '' : 'opacity-60 cursor-not-allowed'; ?> <?php echo $user && is_liked((int)$r['id'], (int)$user['id']) ? 'text-red-500' : 'text-gray-400'; ?>">
                        <i class="icon-transition <?php echo $user && is_liked((int)$r['id'], (int)$user['id']) ? 'fas' : 'far'; ?> fa-heart"></i>
                    </button>
                   <div id="like-count-wrapper-<?php echo (int)$r['id']; ?>" class="text-[16px]<?php echo ((int)$r['likes_count'] === 0 ? ' hidden' : ''); ?>">
                          <span id="like-count-<?php echo (int)$r['id']; ?>"><?php echo (int)$r['likes_count']; ?></span>
                    </div>
                    </div>
                    <?php if ($user): ?>
                        <button id="favorite-btn-<?php echo (int)$r['id']; ?>" onclick="toggleFavorite(<?php echo (int)$r['id']; ?>)" class="favorite-btn text-xl <?php echo is_favorited((int)$r['id'], (int)$user['id']) ? 'text-yellow-500' : 'text-gray-400'; ?>">
                            <i class="icon-transition <?php echo is_favorited((int)$r['id'], (int)$user['id']) ? 'fas' : 'far'; ?> fa-bookmark"></i>
                        </button>
                    <?php endif; ?>
                    <div class="ml-auto text-sm text-gray-600 flex items-center gap-3">
                        <span><i class="fas fa-cog mr-1"></i>Schwierigkeit: <strong><?php
                                                                                    echo match ($r['difficulty']) {
                                                                                        'easy' => 'Leicht',
                                                                                        'medium' => 'Mittel',
                                                                                        'hard' => 'Schwer',
                                                                                        default => htmlspecialchars($r['difficulty'])
                                                                                    };
                                                                                    ?></strong></span>
                        <span><i class="fas fa-clock mr-1"></i>Dauer: <strong><?php echo (int)$r['duration_minutes']; ?> Min</strong></span>
                        <?php if (!empty($r['portions'])): ?>
                            <span><i class="fas fa-users mr-1"></i>Portionen: <strong><?php echo (int)$r['portions']; ?></strong></span>
                        <?php endif; ?>
                        <?php if (!empty($r['category'])): ?>
                            <span><i class="fas fa-tag mr-1"></i><?php echo htmlspecialchars($r['category']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <h2 class="text-lg font-semibold"><a href="<?php echo htmlspecialchars(recipe_url($r)); ?>"><?php echo htmlspecialchars($r['title']); ?></a></h2>
                <p class="text-sm leading-6 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($r['description'])); ?></p>
            </div>
        </article>
    <?php endforeach; 
    $html = ob_get_clean();

    echo json_encode([
        'ok' => true,
        'html' => $html,
        'hasMore' => $page < $totalPages,
        'nextPage' => $page + 1
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}
