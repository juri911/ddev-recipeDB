<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../config.php';

$profileUser = null;
$uid = 0;

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
}

// If no user found and we have a current user, default to current user
if (!$profileUser) {
    $currentUser = current_user();
    if ($currentUser) {
        $uid = (int)$currentUser['id'];
        $profileUser = $currentUser;
        
        // Redirect to pretty URL
        $cleanName = create_url_slug($profileUser['name']);
        header("Location: /all_my_links/{$cleanName}");
        exit;
    } else {
        // If no user found and not logged in, redirect to home
        header('Location: /');
        exit;
    }
}

// Function to create URL-friendly slug from username
function create_url_slug($name) {
    $map = [ 'ä'=>'ae','ö'=>'oe','ü'=>'ue','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue','ß'=>'ss' ];
    $slug = strtr(trim($name), $map);
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Check if any social links exist
$hasSocialLinks = !empty($profileUser['blog_url']) || !empty($profileUser['website_url']) || 
                  !empty($profileUser['instagram_url']) || !empty($profileUser['twitter_url']) || 
                  !empty($profileUser['facebook_url']) || !empty($profileUser['tiktok_url']) || 
                  !empty($profileUser['youtube_url']);

// If no social links, redirect to profile
if (!$hasSocialLinks) {
    header("Location: /profile.php?uid={$uid}");
    exit;
}

// SEO Meta Tags
$seo = [
    'title' => 'Alle Links von ' . htmlspecialchars($profileUser['name'] ?? 'Nutzer') . ' | ' . APP_NAME,
    'description' => !empty($profileUser['bio']) ? $profileUser['bio'] : 'Entdecke alle Links und Profile von ' . $profileUser['name'],
    'keywords' => htmlspecialchars($profileUser['name']) . ', Links, Social Media, Profil',
    'author' => htmlspecialchars($profileUser['name'] ?? 'Nutzer'),
    'image' => isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png',
];

require_once __DIR__ . '/../lib/csrf.php';
csrf_start();
$csrfToken = csrf_token();

// Include header
include __DIR__ . '/includes/header.php';
?>

<style>
    .link-item {
        position: relative;
        overflow: hidden;
    }
    
    .link-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(45, 126, 247, 0.1), transparent);
        transition: left 0.5s;
    }
    
    .link-item:hover::before {
        left: 100%;
    }
</style>

<!-- Back Button -->
<div class="container mx-auto px-4 mt-5">
    <a href="/profile.php?uid=<?php echo (int)$uid; ?>" 
       class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white hover:bg-gray-700 transition-all duration-300 shadow-lg">
        <i class="fas fa-arrow-left"></i>
        <span>Zurück zum Profil</span>
    </a>
</div>

<!-- Main Container -->
<section class="container mx-auto px-4 py-8 max-w-4xl">
    <!-- Profile Header -->
    <div class="text-center mb-12">
        <div class="relative inline-block mb-6">
            <img src="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>" 
                 class="w-32 h-32 rounded-full object-cover border-4 border-[#2d7ef7] shadow-2xl mx-auto hover:scale-110 transition-all duration-300" 
                 alt="Avatar von <?php echo htmlspecialchars($profileUser['name']); ?>" />
        </div>
        
        <h1 class="text-4xl md:text-5xl font-bold mb-4">
            <?php echo htmlspecialchars($profileUser['name'] ?? ('Nutzer #' . $uid)); ?>
        </h1>
        
        <?php if (!empty($profileUser['bio'])): ?>
            <p class="text-lg text-gray-600 dark:text-gray-400 mb-3 leading-relaxed max-w-2xl mx-auto">
                <?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?>
            </p>
        <?php endif; ?>
        
        <?php if (!empty($profileUser['user_titel'])): ?>
            <p class="text-base text-gray-500 dark:text-gray-500 italic mb-8">
                <?php echo nl2br(htmlspecialchars($profileUser['user_titel'])); ?>
            </p>
        <?php endif; ?>
        
        <!-- Share Button -->
        <button id="shareButton" 
                class="inline-flex items-center gap-2 px-4 py-2 bg-[#2d7ef7] hover:bg-blue-600 text-white rounded-lg shadow-sm hover:shadow-md transition-all duration-200 font-medium group"
                title="Link teilen">
            <i class="fas fa-share-alt text-lg group-hover:scale-110 transition-transform duration-200"></i>
            <span class="text-sm">Teilen</span>
        </button>
    </div>

    <!-- Links Container -->
    <div class="space-y-4">
        <?php 
        $links = [];
        
        if (!empty($profileUser['blog_url'])) {
            $links[] = [
                'url' => $profileUser['blog_url'],
                'icon' => 'fas fa-blog',
                'title' => 'Mein Blog',
                'description' => 'Lies meine neuesten Artikel und Gedanken',
                'color' => 'bg-red-500'
            ];
        }
        
        if (!empty($profileUser['website_url'])) {
            $links[] = [
                'url' => $profileUser['website_url'],
                'icon' => 'fas fa-globe',
                'title' => 'Meine Website',
                'description' => 'Besuche meine offizielle Website',
                'color' => 'bg-teal-500'
            ];
        }
        
        if (!empty($profileUser['instagram_url'])) {
            $links[] = [
                'url' => $profileUser['instagram_url'],
                'icon' => 'fab fa-instagram',
                'title' => 'Instagram',
                'description' => 'Folge mir für tägliche Updates und Fotos',
                'color' => 'bg-pink-500'
            ];
        }
        
        if (!empty($profileUser['twitter_url'])) {
            $links[] = [
                'url' => $profileUser['twitter_url'],
                'icon' => 'fab fa-twitter',
                'title' => 'Twitter / X',
                'description' => 'Meine Gedanken in 280 Zeichen',
                'color' => 'bg-blue-400'
            ];
        }
        
        if (!empty($profileUser['facebook_url'])) {
            $links[] = [
                'url' => $profileUser['facebook_url'],
                'icon' => 'fab fa-facebook',
                'title' => 'Facebook',
                'description' => 'Verbinde dich mit mir auf Facebook',
                'color' => 'bg-blue-600'
            ];
        }
        
        if (!empty($profileUser['tiktok_url'])) {
            $links[] = [
                'url' => $profileUser['tiktok_url'],
                'icon' => 'fab fa-tiktok',
                'title' => 'TikTok',
                'description' => 'Kurze Videos und kreative Inhalte',
                'color' => 'bg-gray-800'
            ];
        }
        
        if (!empty($profileUser['youtube_url'])) {
            $links[] = [
                'url' => $profileUser['youtube_url'],
                'icon' => 'fab fa-youtube',
                'title' => 'YouTube',
                'description' => 'Schaue meine Videos und abonniere meinen Kanal',
                'color' => 'bg-red-600'
            ];
        }
        
        // Add Recipe Link
        $links[] = [
            'url' => '/profile.php?uid=' . $uid,
            'icon' => 'fas fa-utensils',
            'title' => 'Meine Rezepte',
            'description' => 'Entdecke all meine köstlichen Rezepte',
            'color' => 'bg-emerald-600'
        ];
        
        foreach ($links as $link): ?>
            <a href="<?php echo htmlspecialchars($link['url']); ?>" 
               target="<?php echo strpos($link['url'], '/profile.php') === 0 ? '_self' : '_blank'; ?>"
               rel="<?php echo strpos($link['url'], '/profile.php') === 0 ? '' : 'noopener'; ?>"
               class="link-item group bg-white dark:bg-gray-800 rounded-lg p-6 flex items-center gap-4 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 border border-gray-200 dark:border-gray-600 block">
               
                <!-- Icon -->
                <div class="flex-shrink-0 w-14 h-14 <?php echo $link['color']; ?> rounded-lg flex items-center justify-center text-white text-2xl shadow-lg group-hover:scale-110 transition-all duration-300">
                    <i class="<?php echo $link['icon']; ?>"></i>
                </div>
                
                <!-- Content -->
                <div class="flex-1">
                    <h3 class="font-bold text-lg mb-1">
                        <?php echo $link['title']; ?>
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <?php echo $link['description']; ?>
                    </p>
                </div>
                
                <!-- Arrow Icon -->
                <div class="flex-shrink-0 text-gray-400 group-hover:text-[#2d7ef7] group-hover:translate-x-1 transition-all duration-300">
                    <i class="fas fa-chevron-right text-lg"></i>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Footer -->
    <div class="text-center mt-12 text-gray-500 dark:text-gray-400 text-sm">
        <p>Erstellt mit ❤️ für <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?></p>
        <div class="mt-2 text-xs opacity-60">
            Powered by <?php echo APP_NAME; ?>
        </div>
    </div>
</section>


<script>
    // Share functionality
    document.getElementById('shareButton').addEventListener('click', async function() {
        const url = window.location.href;
        const title = document.title;
        const text = 'Schau dir alle meine Links an!';

        if (navigator.share) {
            try {
                await navigator.share({
                    title: title,
                    text: text,
                    url: url
                });
            } catch (err) {
                console.log('Error sharing:', err);
                fallbackShare();
            }
        } else {
            fallbackShare();
        }
    });

    function fallbackShare() {
        const url = window.location.href;
        navigator.clipboard.writeText(url).then(() => {
            // Show success message
            const button = document.getElementById('shareButton');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> <span class="text-sm">Kopiert!</span>';
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
            }, 2000);
        }).catch(() => {
            // Fallback: show URL in alert
            alert('Link kopieren:\n' + url);
        });
    }
</script>

<?php
// Include footer
include __DIR__ . '/includes/footer.php';
?>