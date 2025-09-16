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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alle Links von <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Alle Links und Social Media Profile von <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?> an einem Ort.">
    <meta name="keywords" content="<?php echo htmlspecialchars($profileUser['name']); ?>, Links, Social Media, Profil">
    <meta name="author" content="<?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?>">
    
    <!-- Open Graph Meta Tags für Social Media Sharing -->
    <meta property="og:title" content="Alle Links von <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(!empty($profileUser['bio']) ? $profileUser['bio'] : 'Entdecke alle Links und Profile von ' . $profileUser['name']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>all_my_links/<?php echo create_url_slug($profileUser['name']); ?>">
    <meta property="og:type" content="profile">
    <meta property="og:site_name" content="<?php echo APP_NAME; ?>">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Alle Links von <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars(!empty($profileUser['bio']) ? $profileUser['bio'] : 'Entdecke alle Links und Profile von ' . $profileUser['name']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'gradient': 'gradient 8s ease infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'slideInLeft': 'slideInLeft 0.6s ease-out',
                        'slideInDown': 'slideInDown 0.8s ease-out',
                        'fadeInUp': 'fadeInUp 0.8s ease-out',
                        'shimmer': 'shimmer 2s linear infinite',
                        'bounce-slow': 'bounce 3s infinite',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    keyframes: {
                        gradient: {
                            '0%, 100%': {
                                'background-size': '200% 200%',
                                'background-position': 'left center'
                            },
                            '50%': {
                                'background-size': '200% 200%',
                                'background-position': 'right center'
                            }
                        },
                        float: {
                            '0%, 100%': { transform: 'translateY(0px) rotate(0deg)' },
                            '50%': { transform: 'translateY(-20px) rotate(180deg)' }
                        },
                        slideInLeft: {
                            '0%': { opacity: '0', transform: 'translateX(-30px)' },
                            '100%': { opacity: '1', transform: 'translateX(0)' }
                        },
                        slideInDown: {
                            '0%': { opacity: '0', transform: 'translateY(-30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        shimmer: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(100%)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .bg-animated-gradient {
            background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c, #4facfe, #00f2fe, #43e97b, #38f9d7);
            background-size: 400% 400%;
            animation: gradient 8s ease infinite;
        }
        
        .link-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }
        
        .link-item:hover::before {
            left: 100%;
        }
        
        .particle {
            position: fixed;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            pointer-events: none;
            animation: float 6s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-animated-gradient min-h-screen p-5 overflow-x-hidden">
    <!-- Floating Particles -->
    <div id="particles" class="fixed inset-0 pointer-events-none z-0"></div>
    
    <!-- Back Button -->
    <a href="/profile.php?uid=<?php echo (int)$uid; ?>" 
       class="fixed top-5 left-5 z-50 bg-white/90 backdrop-blur-lg rounded-full px-5 py-3 text-gray-800 font-semibold flex items-center gap-2 shadow-lg hover:bg-white hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
        <i class="fas fa-arrow-left"></i>
        Zurück zum Profil
    </a>

    <!-- Share Button -->
    <button id="shareButton" 
            class="fixed top-5 right-5 z-50 bg-white/90 backdrop-blur-lg rounded-full p-3 text-gray-800 shadow-lg hover:bg-white hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
            title="Link teilen">
        <i class="fas fa-share-alt"></i>
    </button>

    <!-- Main Container -->
    <div class="max-w-2xl mx-auto relative z-10 animate-fadeInUp">
        <!-- Profile Header -->
        <div class="text-center mb-12 animate-slideInDown" style="animation-delay: 0.2s; animation-fill-mode: both;">
            <div class="relative inline-block mb-6">
                <img src="<?php echo htmlspecialchars(isset($profileUser['avatar_path']) && $profileUser['avatar_path'] ? absolute_url_from_path((string)$profileUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>" 
                     class="w-32 h-32 rounded-full object-cover border-4 border-white/80 shadow-2xl mx-auto hover:scale-105 hover:rotate-2 transition-all duration-300" 
                     alt="Avatar von <?php echo htmlspecialchars($profileUser['name']); ?>" />
                <div class="absolute -inset-1 bg-gradient-to-r from-pink-500 via-purple-500 to-blue-500 rounded-full blur opacity-30 animate-pulse-slow"></div>
            </div>
            
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4 drop-shadow-lg animate-bounce-slow">
                <?php echo htmlspecialchars($profileUser['name'] ?? ('Nutzer #' . $uid)); ?>
            </h1>
            
            <?php if (!empty($profileUser['bio'])): ?>
                <p class="text-lg text-white/90 mb-3 drop-shadow-md leading-relaxed max-w-md mx-auto">
                    <?php echo nl2br(htmlspecialchars($profileUser['bio'])); ?>
                </p>
            <?php endif; ?>
            
            <?php if (!empty($profileUser['user_titel'])): ?>
                <p class="text-base text-white/80 italic drop-shadow-sm mb-8">
                    <?php echo nl2br(htmlspecialchars($profileUser['user_titel'])); ?>
                </p>
            <?php endif; ?>
            
            <!-- URL Display -->
            <div class="bg-white/20 backdrop-blur-sm rounded-full px-4 py-2 inline-block text-white/80 text-sm font-mono">
                <i class="fas fa-link mr-2"></i>
                <?php echo SITE_URL; ?>all_my_links/<?php echo create_url_slug($profileUser['name']); ?>
            </div>
        </div>

        <!-- Links Container -->
        <div class="space-y-4">
            <?php 
            $delay = 1;
            $links = [];
            
            if (!empty($profileUser['blog_url'])) {
                $links[] = [
                    'url' => $profileUser['blog_url'],
                    'icon' => 'fas fa-blog',
                    'title' => 'Mein Blog',
                    'description' => 'Lies meine neuesten Artikel und Gedanken',
                    'color' => 'from-red-400 to-pink-500',
                    'border' => 'border-l-red-400'
                ];
            }
            
            if (!empty($profileUser['website_url'])) {
                $links[] = [
                    'url' => $profileUser['website_url'],
                    'icon' => 'fas fa-globe',
                    'title' => 'Meine Website',
                    'description' => 'Besuche meine offizielle Website',
                    'color' => 'from-teal-400 to-cyan-500',
                    'border' => 'border-l-teal-400'
                ];
            }
            
            if (!empty($profileUser['instagram_url'])) {
                $links[] = [
                    'url' => $profileUser['instagram_url'],
                    'icon' => 'fab fa-instagram',
                    'title' => 'Instagram',
                    'description' => 'Folge mir für tägliche Updates und Fotos',
                    'color' => 'from-pink-500 to-rose-500',
                    'border' => 'border-l-pink-500'
                ];
            }
            
            if (!empty($profileUser['twitter_url'])) {
                $links[] = [
                    'url' => $profileUser['twitter_url'],
                    'icon' => 'fab fa-twitter',
                    'title' => 'Twitter / X',
                    'description' => 'Meine Gedanken in 280 Zeichen',
                    'color' => 'from-blue-400 to-blue-500',
                    'border' => 'border-l-blue-400'
                ];
            }
            
            if (!empty($profileUser['facebook_url'])) {
                $links[] = [
                    'url' => $profileUser['facebook_url'],
                    'icon' => 'fab fa-facebook',
                    'title' => 'Facebook',
                    'description' => 'Verbinde dich mit mir auf Facebook',
                    'color' => 'from-blue-600 to-indigo-600',
                    'border' => 'border-l-blue-600'
                ];
            }
            
            if (!empty($profileUser['tiktok_url'])) {
                $links[] = [
                    'url' => $profileUser['tiktok_url'],
                    'icon' => 'fab fa-tiktok',
                    'title' => 'TikTok',
                    'description' => 'Kurze Videos und kreative Inhalte',
                    'color' => 'from-gray-800 to-black',
                    'border' => 'border-l-gray-800'
                ];
            }
            
            if (!empty($profileUser['youtube_url'])) {
                $links[] = [
                    'url' => $profileUser['youtube_url'],
                    'icon' => 'fab fa-youtube',
                    'title' => 'YouTube',
                    'description' => 'Schaue meine Videos und abonniere meinen Kanal',
                    'color' => 'from-red-500 to-red-600',
                    'border' => 'border-l-red-500'
                ];
            }
            
            // Add Recipe Link
            $links[] = [
                'url' => '/profile.php?uid=' . $uid,
                'icon' => 'fas fa-utensils',
                'title' => 'Meine Rezepte',
                'description' => 'Entdecke all meine köstlichen Rezepte',
                'color' => 'from-emerald-500 to-green-600',
                'border' => 'border-l-emerald-500'
            ];
            
            foreach ($links as $link): ?>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                   target="<?php echo strpos($link['url'], '/profile.php') === 0 ? '_self' : '_blank'; ?>"
                   rel="<?php echo strpos($link['url'], '/profile.php') === 0 ? '' : 'noopener'; ?>"
                   class="link-item group relative bg-white/95 backdrop-blur-sm rounded-2xl p-6 flex items-center gap-4 hover:bg-white hover:shadow-2xl hover:-translate-y-2 hover:scale-105 transition-all duration-300 border-l-4 <?php echo $link['border']; ?> overflow-hidden animate-slideInLeft block"
                   style="animation-delay: <?php echo $delay * 0.1; ?>s; animation-fill-mode: both;">
                   
                    <!-- Animated Background Gradient -->
                    <div class="absolute inset-0 bg-gradient-to-r <?php echo $link['color']; ?> opacity-0 group-hover:opacity-5 transition-opacity duration-300"></div>
                    
                    <!-- Icon -->
                    <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br <?php echo $link['color']; ?> rounded-xl flex items-center justify-center text-white text-2xl shadow-lg group-hover:rotate-12 group-hover:scale-110 transition-all duration-300">
                        <i class="<?php echo $link['icon']; ?>"></i>
                    </div>
                    
                    <!-- Content -->
                    <div class="flex-1 relative z-10">
                        <h3 class="font-bold text-lg text-gray-800 group-hover:text-gray-900 mb-1">
                            <?php echo $link['title']; ?>
                        </h3>
                        <p class="text-sm text-gray-600 group-hover:text-gray-700">
                            <?php echo $link['description']; ?>
                        </p>
                    </div>
                    
                    <!-- Arrow Icon -->
                    <div class="flex-shrink-0 text-gray-400 group-hover:text-gray-600 group-hover:translate-x-1 transition-all duration-300">
                        <i class="fas fa-chevron-right text-lg"></i>
                    </div>
                </a>
            <?php 
                $delay++;
            endforeach; ?>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-12 text-white/70 text-sm">
            <p>Erstellt mit ❤️ für <?php echo htmlspecialchars($profileUser['name'] ?? 'Nutzer'); ?></p>
            <div class="mt-2 text-xs opacity-60">
                Powered by <?php echo APP_NAME; ?>
            </div>
        </div>
    </div>

    <script>
        // Create floating particles
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 20;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 8 + 3;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
                
                container.appendChild(particle);
            }
        }

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
                button.innerHTML = '<i class="fas fa-check text-green-600"></i>';
                
                setTimeout(() => {
                    button.innerHTML = originalHTML;
                }, 2000);
            }).catch(() => {
                // Fallback: show URL in alert
                alert('Link kopieren:\n' + url);
            });
        }

        // Add click ripple effect
        document.querySelectorAll('.link-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                ripple.className = 'absolute rounded-full bg-white/30 pointer-events-none animate-ping';
                ripple.style.cssText = `
                    left: ${x - 10}px;
                    top: ${y - 10}px;
                    width: 20px;
                    height: 20px;
                `;
                
                this.appendChild(ripple);
                
                setTimeout(() => ripple.remove(), 1000);
            });
        });

        // Initialize particles
        createParticles();

        // Add parallax effect
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                const speed = 0.3 + (index % 4) * 0.2;
                particle.style.transform = `translateY(${scrolled * speed}px) rotate(${scrolled * 0.1}deg)`;
            });
        });
    </script>
</body>
</html>