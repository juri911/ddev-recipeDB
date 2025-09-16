<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/notifications.php';
require_once __DIR__ . '/../config.php';

$user = current_user();

$profileUser = null;
$uid = 0;

// Function to create URL-friendly slug from username
function create_url_slug($name) {
    $map = [ 'ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss' ];
    $slug = strtr(trim($name), $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Check if we have a user_name parameter (pretty URL)
if (isset($_GET['user_name']) && !empty($_GET['user_name'])) {
    $userName = trim($_GET['user_name']);
    
    // Find user by name (case-insensitive)
    $stmt = db_query('SELECT * FROM users WHERE LOWER(name) = LOWER(?)', [$userName]);
    $profileUser = $stmt->fetch();
    
    if ($profileUser) {
        $uid = (int)$profileUser['id'];
    }
}
// Fallback to uid parameter for backward compatibility
elseif (isset($_GET['uid']) && !empty($_GET['uid'])) {
    $uid = (int)$_GET['uid'];
    $profileUser = get_user_by_id($uid);
    
    // If we found a user with UID, redirect to pretty URL
    if ($profileUser) {
        $userSlug = create_url_slug($profileUser['name']);
        header("Location: /profile/{$userSlug}");
        exit;
    }
}

// If no user found and we have a current user, default to current user
if (!$profileUser) {
    if ($user) {
        $uid = (int)$user['id'];
        $profileUser = $user;
        
        // Redirect to pretty URL
        $userSlug = create_url_slug($profileUser['name']);
        header("Location: /profile/{$userSlug}");
        exit;
    } else {
        // If no user found and not logged in, redirect to home
        header('Location: /');
        exit;
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$total = count_user_recipes($uid);
$recipes = get_user_recipes($uid, $perPage, $offset);
$totalPages = max(1, (int)ceil($total / $perPage));

// Get favorites and following users for current user's profile
$favorites = [];
$followingUsers = [];
$followersUsers = [];
if ($user && (int)$user['id'] === $uid) {
    $favorites = get_user_favorites($uid, 6, 0); // Show first 6 favorites
    $followingUsers = get_following_users($uid, 6, 0); // Show first 6 following
    $followersUsers = get_followers_users($uid, 6, 0); // Show first 6 followers
}

// Start CSRF session
csrf_start();

// Set page title and CSRF token for header
$pageTitle = htmlspecialchars($profileUser['name'] ?? 'Nutzer') . ' - ' . APP_NAME;
$csrfToken = csrf_token();

// Include global header
include __DIR__ . '/includes/header.php';
?>

<!-- SEO Meta Tags (should be in header.php, but shown here for reference) -->
<meta name="description" content="Profil von <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?> - Entdecke leckere Rezepte und folge für Updates.">
<meta name="keywords" content="<?php echo htmlspecialchars($profileUser['name']); ?>, Rezepte, Kochen, Profil">
<meta name="author" content="<?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?>">

<!-- Open Graph Meta Tags -->
<meta property="og:title" content="<?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?> - Profil">
<meta property="og:description" content="<?php echo htmlspecialchars(!empty($profileUser['bio']) ? $profileUser['bio'] : 'Entdecke leckere Rezepte von ' . $profileUser['name']); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>">
<meta property="og:url" content="<?php echo SITE_URL; ?>profile/<?php echo create_url_slug($profileUser['name']); ?>">
<meta property="og:type" content="profile">
<meta property="og:site_name" content="<?php echo APP_NAME; ?>">

<div class="flex items-start gap-4 mb-6">
    <img src="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>" class="h-20 w-20 rounded-full object-cover bg-gray-200" alt="Avatar" />
    <div class="flex-1">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-semibold"><?php echo htmlspecialchars($profileUser['name'] ?? ('Nutzer #' . $uid)); ?></h1>
            
            <?php if ($user && (int)$user['id'] === $uid): ?>
                <a class="text-sm px-3 py-1 border rounded hover:bg-gray-50 transition-colors" href="/profile_edit.php">Profil bearbeiten</a>
            <?php elseif ($user): ?>
                <?php
                $isFollowing = is_following((int)$user['id'], $uid);
                $followText = $isFollowing ? 'Entfolgen' : 'Folgen';
                $followClass = $isFollowing ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-blue-600 text-white hover:bg-blue-700';
                ?>
                <button
                    id="follow-button"
                    class="text-sm px-3 py-1 rounded transition-colors duration-200 <?php echo $followClass; ?>"
                    data-profile-id="<?php echo $uid; ?>"
                    data-is-following="<?php echo $isFollowing ? 'true' : 'false'; ?>"><?php echo $followText; ?></button>
            <?php endif; ?>
            
            <!-- Share Profile Button -->
            <button onclick="shareProfile()" 
                    class="text-sm px-3 py-1 border rounded hover:bg-gray-50 transition-colors"
                    title="Profil teilen">
                <i class="fas fa-share-alt mr-1"></i>
                Teilen
            </button>
        </div>
        
        <!-- Pretty URL Display -->
        <div class="text-xs text-gray-500 mt-1 font-mono">
            <?php echo SITE_URL; ?>profile/<?php echo create_url_slug($profileUser['name']); ?>
        </div>
        
        <div class="flex gap-4 mt-2 text-sm text-gray-700">
            <div><strong id="followers-count"><?php echo get_followers_count($uid); ?></strong> Follower</div>
            <div><strong id="following-count"><?php echo get_following_count($uid); ?></strong> Following</div>
        </div>
        <?php if (!empty($profileUser['bio'])): ?>
            <p class="text-sm text-gray-700 mt-2 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?></p>
        <?php endif; ?>
        <?php if (!empty($profileUser['user_titel'])): ?>
            <p class="text-sm text-gray-700 mt-2 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($profileUser['user_titel'])); ?></p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-3 mt-3 text-sm">
            <?php if (!empty($profileUser['blog_url'])): ?>
                <a class="text-blue-600 hover:text-blue-800 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['blog_url']); ?>" target="_blank" rel="noopener" title="Blog">
                    <i class="social-icon fas fa-blog text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['website_url'])): ?>
                <a class="text-blue-600 hover:text-blue-800 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['website_url']); ?>" target="_blank" rel="noopener" title="Website">
                    <i class="social-icon fas fa-globe text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['instagram_url'])): ?>
                <a class="text-pink-600 hover:text-pink-800 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['instagram_url']); ?>" target="_blank" rel="noopener" title="Instagram">
                    <i class="social-icon fab fa-instagram text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['twitter_url'])): ?>
                <a class="text-blue-500 hover:text-blue-700 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['twitter_url']); ?>" target="_blank" rel="noopener" title="Twitter/X">
                    <i class="social-icon fab fa-twitter text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['facebook_url'])): ?>
                <a class="text-blue-700 hover:text-blue-900 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['facebook_url']); ?>" target="_blank" rel="noopener" title="Facebook">
                    <i class="social-icon fab fa-facebook text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['tiktok_url'])): ?>
                <a class="text-gray-800 hover:text-gray-900 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['tiktok_url']); ?>" target="_blank" rel="noopener" title="TikTok">
                    <i class="social-icon fab fa-tiktok text-lg"></i>
                </a>
            <?php endif; ?>
            <?php if (!empty($profileUser['youtube_url'])): ?>
                <a class="text-red-600 hover:text-red-800 transition-colors duration-200" href="<?php echo htmlspecialchars($profileUser['youtube_url']); ?>" target="_blank" rel="noopener" title="YouTube">
                    <i class="social-icon fab fa-youtube text-lg"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- AllMyLinks Button with Pretty URL -->
        <?php if (!empty($profileUser['blog_url']) || !empty($profileUser['website_url']) || !empty($profileUser['instagram_url']) || !empty($profileUser['twitter_url']) || !empty($profileUser['facebook_url']) || !empty($profileUser['tiktok_url']) || !empty($profileUser['youtube_url'])): ?>
            <?php $userSlug = create_url_slug($profileUser['name']); ?>
            <div class="mt-4 flex gap-2 flex-wrap">
                <a href="/all_my_links/<?php echo htmlspecialchars($userSlug); ?>" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold rounded-full shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300 group">
                    <i class="fas fa-link group-hover:rotate-12 transition-transform duration-300"></i>
                    <span>Alle meine Links</span>
                    <i class="fas fa-external-link-alt text-sm opacity-70"></i>
                </a>
                
                <!-- Copy AllMyLinks Button -->
                <button onclick="copyLinkToClipboard('/all_my_links/<?php echo htmlspecialchars($userSlug); ?>')" 
                        class="inline-flex items-center gap-2 px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-full shadow-md hover:shadow-lg transition-all duration-300"
                        title="AllMyLinks Link kopieren">
                    <i class="fas fa-copy"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<h2 class="text-xl font-semibold mb-3">Rezepte</h2>
<?php if (empty($recipes)): ?>
    <div class="text-gray-600">Keine Rezepte vorhanden.</div>
    <a class="px-3 py-1 rounded bg-emerald-600 text-white text-sm" href="/recipe_new.php">Neues Rezept</a>
<?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <?php foreach ($recipes as $r): ?>
            <div class="flex flex-col">
                <a href="<?php echo htmlspecialchars(recipe_url($r)); ?>" class="block group">
                    <?php if (!empty($r['images'])): ?>
                        <img src="/<?php echo htmlspecialchars($r['images'][0]['file_path']); ?>" class="w-full h-40 object-cover group-hover:opacity-90 transition" alt="Bild" />
                    <?php else: ?>
                        <div class="w-full h-40 bg-gray-100"></div>
                    <?php endif; ?>
                    <div class="p-2 text-sm truncate font-semibold group-hover:underline"><?php echo htmlspecialchars($r['title']); ?></div>
                </a>
                <?php if ($user && (int)$user['id'] === $uid): ?>
                    <div class="flex items-center gap-2 text-sm">
                        <a class="text-blue-600 hover:text-blue-800 transition-colors" href="/recipe_edit.php?id=<?php echo (int)$r['id']; ?>">Bearbeiten</a>
                        <form method="post" action="/recipe_delete.php" onsubmit="return confirm('Rezept wirklich löschen?');" class="inline">
                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <button class="text-red-600 hover:text-red-800 transition-colors">Löschen</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination with Pretty URLs -->
    <div class="flex items-center justify-center gap-2 mt-8">
        <?php if ($page > 1): ?>
            <a class="px-3 py-1 border rounded hover:bg-gray-50 transition-colors" 
               href="/profile/<?php echo create_url_slug($profileUser['name']); ?>?page=<?php echo $page - 1; ?>">Zurück</a>
        <?php endif; ?>
        <span class="text-sm text-gray-600">Seite <?php echo $page; ?> von <?php echo $totalPages; ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="px-3 py-1 border rounded hover:bg-gray-50 transition-colors" 
               href="/profile/<?php echo create_url_slug($profileUser['name']); ?>?page=<?php echo $page + 1; ?>">Weiter</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Favorites Section (only for own profile) -->
<?php if ($user && (int)$user['id'] === $uid && !empty($favorites)): ?>
    <section class="mt-8 bg-white border rounded-lg p-4">
        <h2 class="text-xl font-semibold mb-3">Meine Favoriten</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php foreach ($favorites as $f): ?>
                <a href="<?php echo htmlspecialchars(recipe_url($f)); ?>" class="block bg-white border rounded overflow-hidden hover:shadow-md transition-shadow">
                    <?php if (!empty($f['images'])): ?>
                        <img src="/<?php echo htmlspecialchars($f['images'][0]['file_path']); ?>" class="w-full h-40 object-cover" alt="Bild" />
                    <?php else: ?>
                        <div class="w-full h-40 bg-gray-100"></div>
                    <?php endif; ?>
                    <div class="p-2 text-sm truncate"><?php echo htmlspecialchars($f['title']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($favorites) >= 6): ?>
            <div class="mt-4 text-center">
                <a href="/favorites.php" class="text-blue-600 hover:text-blue-800 text-sm">Alle Favoriten anzeigen</a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Following Section (only for own profile) -->
<?php if ($user && (int)$user['id'] === $uid && !empty($followingUsers)): ?>
    <section class="mt-8 bg-white border rounded-lg p-4">
        <h2 class="text-xl font-semibold mb-3">Benutzer, denen ich folge</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php foreach ($followingUsers as $fu): ?>
                <a href="/profile/<?php echo create_url_slug($fu['name']); ?>" class="block bg-white border rounded overflow-hidden hover:shadow-md transition-shadow p-3 text-center">
                    <img src="<?php echo htmlspecialchars(isset($fu['avatar_path']) && $fu['avatar_path'] ? absolute_url_from_path((string)$fu['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>" class="h-16 w-16 rounded-full object-cover bg-gray-200 mx-auto mb-2" alt="Avatar" />
                    <div class="text-sm font-medium truncate"><?php echo htmlspecialchars($fu['name']); ?></div>
                    <?php if (!empty($fu['bio'])): ?>
                        <div class="text-xs text-gray-500 truncate mt-1"><?php echo htmlspecialchars($fu['bio']); ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($followingUsers) >= 6): ?>
            <div class="mt-4 text-center">
                <a href="/following.php" class="text-blue-600 hover:text-blue-800 text-sm">Alle gefolgten Benutzer anzeigen</a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Followers Section (only for own profile) -->
<?php if ($user && (int)$user['id'] === $uid && !empty($followersUsers)): ?>
    <section class="mt-8 bg-white border rounded-lg p-4">
        <h2 class="text-xl font-semibold mb-3">Meine Follower</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            <?php foreach ($followersUsers as $fu): ?>
                <a href="/profile/<?php echo create_url_slug($fu['name']); ?>" class="block bg-white border rounded overflow-hidden hover:shadow-md transition-shadow p-3 text-center">
                    <img src="<?php echo htmlspecialchars(isset($fu['avatar_path']) && $fu['avatar_path'] ? absolute_url_from_path((string)$fu['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>" class="h-16 w-16 rounded-full object-cover bg-gray-200 mx-auto mb-2" alt="Avatar" />
                    <div class="text-sm font-medium truncate"><?php echo htmlspecialchars($fu['name']); ?></div>
                    <?php if (!empty($fu['bio'])): ?>
                        <div class="text-xs text-gray-500 truncate mt-1"><?php echo htmlspecialchars($fu['bio']); ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($followersUsers) >= 6): ?>
            <div class="mt-4 text-center">
                <a href="/followers.php" class="text-blue-600 hover:text-blue-800 text-sm">Alle Follower anzeigen</a>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Page specific JavaScript -->
<script>
    // CSRF token from PHP session
    const csrfToken = '<?php echo htmlspecialchars(csrf_token()); ?>';

    document.addEventListener('DOMContentLoaded', () => {
        const followButton = document.getElementById('follow-button');
        if (followButton) {
            followButton.addEventListener('click', async () => {
                const profileId = followButton.dataset.profileId;
                const isFollowing = followButton.dataset.isFollowing === 'true';

                try {
                    const response = await fetch('/api/toggle_follow.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            profile_id: profileId,
                            csrf_token: csrfToken
                        })
                    });
                    const result = await response.json();

                    if (result.ok) {
                        followButton.dataset.isFollowing = result.following ? 'true' : 'false';
                        followButton.textContent = result.following ? 'Entfolgen' : 'Folgen';
                        followButton.classList.toggle('bg-red-600', result.following);
                        followButton.classList.toggle('hover:bg-red-700', result.following);
                        followButton.classList.toggle('bg-blue-600', !result.following);
                        followButton.classList.toggle('hover:bg-blue-700', !result.following);
                        // Only update followers count - this shows how many people follow the profile owner
                        document.getElementById('followers-count').textContent = result.followersCount;
                        // Don't update following count - that shows how many the profile owner follows
                    } else {
                        alert('Fehler: ' + result.error);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Netzwerkfehler beim Folgen/Entfolgen.');
                }
            });
        }
    });

    // Share profile function
    async function shareProfile() {
        const url = window.location.href;
        const title = '<?php echo addslashes(htmlspecialchars($profileUser['name'] ?? 'Nutzer')); ?> - Profil';
        const text = 'Schau dir das Profil von <?php echo addslashes(htmlspecialchars($profileUser['name'] ?? 'Nutzer')); ?> an!';

        if (navigator.share) {
            try {
                await navigator.share({
                    title: title,
                    text: text,
                    url: url
                });
            } catch (err) {
                console.log('Error sharing:', err);
                fallbackCopy(url);
            }
        } else {
            fallbackCopy(url);
        }
    }

    // Copy link to clipboard function
    function copyLinkToClipboard(path) {
        const fullUrl = window.location.origin + path;
        navigator.clipboard.writeText(fullUrl).then(() => {
            // Visual feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check text-green-600"></i>';
            button.classList.add('bg-green-100', 'text-green-700');
            button.classList.remove('bg-gray-100', 'text-gray-700');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-100', 'text-green-700');
                button.classList.add('bg-gray-100', 'text-gray-700');
            }, 2000);
        }).catch(() => {
            alert('Link: ' + fullUrl);
        });
    }

    function fallbackCopy(url) {
        navigator.clipboard.writeText(url).then(() => {
            // Show success message
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check mr-1"></i>Kopiert!';
            button.classList.add('bg-green-100', 'text-green-700');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.remove('bg-green-100', 'text-green-700');
            }, 2000);
        }).catch(() => {
            alert('Profil-Link: ' + url);
        });
    }
</script>

<?php
// Include global footer
include __DIR__ . '/includes/footer.php';
?>