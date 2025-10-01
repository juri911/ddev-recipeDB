<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

require_login();
$user = current_user();
$dbUser = get_user_by_id((int) $user['id']);

$error = null;
$success = null;
csrf_start();

// Profil löschen zuerst prüfen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_profile'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } elseif (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'Profil löschen') {
        // Profil löschen
        delete_user_by_id((int) $user['id']);
        session_destroy();
        header('Location: /login.php?deleted=1');
        exit;
    } else {
        $error = "Bitte geben Sie zur Bestätigung 'Profil löschen' ein.";
    }
}

// Profil bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_profile'])) {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        try {
            // Check if avatar should be deleted
            $avatarFile = null;
            $deleteAvatar = false;

            if (isset($_POST['delete_avatar']) && $_POST['delete_avatar'] === '1') {
                $deleteAvatar = true;
            } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $avatarFile = $_FILES['avatar'];
            }

            // Link-Felder definieren
            $linkFields = [
                'blog_url',
                'website_url',
                'instagram_url',
                'twitter_url',
                'facebook_url',
                'tiktok_url',
                'youtube_url'
            ];



            // Funktion: leere Strings zu NULL für beliebige Felder
            function empty_to_null_array(array $data, array $fields): array
            {
                foreach ($fields as $field) {
                    if (!isset($data[$field]) || $data[$field] === '') {
                        $data[$field] = null;
                    }
                }
                return $data;
            }

            // Link-Daten zusammenbauen
            $linkData = array_fill_keys($linkFields, '');
            if (isset($_POST['link_type'], $_POST['link_value'])) {
                foreach ($_POST['link_type'] as $idx => $type) {
                    $type = in_array($type, $linkFields) ? $type : null;
                    $value = trim($_POST['link_value'][$idx] ?? '');
                    if ($type && $value !== '') {
                        $linkData[$type] = $value;
                    }
                }
            }

            // User Titel
            $linkData['user_titel'] = trim($_POST['user_titel'] ?? '');

            // Bio hinzufügen
            $linkData['bio'] = trim($_POST['bio'] ?? '');

            // Alle leeren Felder auf NULL setzen (inkl. Bio)
            $linkData = empty_to_null_array($linkData, array_merge($linkFields, ['bio', 'user_titel']));

            // Dann updaten
            $updatedUser = update_user_profile((int) $user['id'], [
                'user_titel' => $linkData['user_titel'],
                'bio' => $linkData['bio'],
                'blog_url' => $linkData['blog_url'],
                'website_url' => $linkData['website_url'],
                'instagram_url' => $linkData['instagram_url'],
                'twitter_url' => $linkData['twitter_url'],
                'facebook_url' => $linkData['facebook_url'],
                'tiktok_url' => $linkData['tiktok_url'],
                'youtube_url' => $linkData['youtube_url'],
            ], $avatarFile, $deleteAvatar);

            // Session-Avatar aktualisieren
            if ($updatedUser) {
                if ($updatedUser['avatar_path'] !== null) {
                    $_SESSION['user']['avatar_path'] = $updatedUser['avatar_path'];
                } else {
                    unset($_SESSION['user']['avatar_path']);
                }
            }

            // Redirect nur, wenn Avatar nicht gelöscht wurde
            if (!$deleteAvatar) {
                header('Location: ' . profile_url(['id' => (int) $user['id'], 'name' => (string) $user['name']]));
                exit;
            }
            $success = 'Avatar wurde erfolgreich gelöscht.';
        } catch (Throwable $e) {
            $error = 'Profil konnte nicht aktualisiert werden';
        }
	}
}

$pageTitle = 'Profil bearbeiten - ' . APP_NAME;
$csrfToken = csrf_token();

include __DIR__ . '/includes/header.php';
?>
<section class="min-h-screen w-full">
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <a href="javascript:history.back()" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 mb-4">
                <i class="fas fa-arrow-left mr-2"></i>Zurück
            </a>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Profil bearbeiten</h1>
            <p class="text-gray-600 dark:text-gray-400">Aktualisiere deine Profilinformationen und Social Links</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-700"><?php echo htmlspecialchars($success); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <span class="text-green-700">Profil wurde erfolgreich gelöscht.</span>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            
            <!-- Basic Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Grundinformationen</h2>
                
                <div class="space-y-6">
                    <!-- Avatar Section -->
                    <div class="flex flex-col lg:flex-row items-start lg:items-center gap-6">
                        <!-- Avatar Preview -->
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars(isset($dbUser['avatar_path']) && $dbUser['avatar_path'] ? absolute_url_from_path((string) $dbUser['avatar_path']) : '/images/default_avatar.png'); ?>"
                                id="avatarPreview" class="h-32 w-32 rounded-full object-cover bg-gray-200 border-4 border-gray-300 dark:border-gray-600" alt="Avatar" />
                            <div class="absolute -bottom-2 -right-2 bg-[#2d7ef7] text-white rounded-full p-2 shadow-lg">
                                <i class="fas fa-camera text-sm"></i>
                            </div>
                        </div>
                        
                        <!-- Avatar Upload Area -->
                        <div class="flex-1 w-full">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Avatar</label>
                            
                            <!-- Drag & Drop Area -->
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-[#2d7ef7] transition-colors cursor-pointer" 
                                 id="avatarUploadArea">
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" 
                                       class="hidden" />
                                <div class="flex flex-col items-center">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-3"></i>
                                    <p class="text-gray-600 dark:text-gray-400 mb-1">Avatar hochladen</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-500">Klicken oder Bild hierher ziehen</p>
                                </div>
                            </div>
                            
                            <!-- Avatar Actions -->
                            <div class="mt-4 flex flex-wrap gap-3">
                                <?php if ($dbUser['avatar_path']): ?>
                                    <button type="submit" name="delete_avatar" value="1"
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-colors duration-200"
                                        onclick="return confirm('Avatar wirklich löschen?')">
                                        <i class="fas fa-trash"></i>
                                        <span>Avatar löschen</span>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" id="selectAvatarBtn" 
                                        class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-[#2d7ef7] hover:bg-blue-600 text-white rounded-lg transition-colors duration-200">
                                    <i class="fas fa-image"></i>
                                    <span>Bild auswählen</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Name</label>
                        <input disabled type="text" name="name" value="<?php echo htmlspecialchars($dbUser['name']); ?>"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400" />
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Der Name kann nicht geändert werden</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titel</label>
                        <input name="user_titel" type="text" placeholder="z.B. Chef-Koch, Food-Blogger, Hobby-Bäcker"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" 
                            value="<?php echo htmlspecialchars($dbUser['user_titel'] ?? ''); ?>" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bio</label>
                        <textarea name="bio" rows="4" placeholder="Erzähle etwas über dich..."
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"><?php echo htmlspecialchars($dbUser['bio'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <!-- Social Links Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Social Links</h2>
                
                <div id="links-section" class="space-y-4">
                    <?php
                    $linkFields = [
                        'blog_url' => 'Blog',
                        'website_url' => 'Webseite',
                        'instagram_url' => 'Instagram',
                        'twitter_url' => 'Twitter/X',
                        'facebook_url' => 'Facebook',
                        'tiktok_url' => 'TikTok',
                        'youtube_url' => 'YouTube',
                    ];

                    $existingLinks = [];
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_type'], $_POST['link_value'])) {
                        foreach ($_POST['link_type'] as $idx => $type) {
                            $existingLinks[] = [
                                'type' => $type,
                                'value' => $_POST['link_value'][$idx] ?? ''
                            ];
                        }
                    } else {
                        foreach ($linkFields as $field => $label) {
                            if (!empty($dbUser[$field])) {
                                $existingLinks[] = [
                                    'type' => $field,
                                    'value' => $dbUser[$field]
                                ];
                            }
                        }
                    }
                    if (empty($existingLinks)) {
                        $existingLinks[] = ['type' => 'blog_url', 'value' => ''];
                    }
                    foreach ($existingLinks as $link):
                        ?>
                        <div class="flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg link-row">
                            <select name="link_type[]" class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" onchange="updateLinkTypeOptions()">
                                <?php foreach ($linkFields as $key => $lbl): ?>
                                    <option value="<?php echo $key; ?>" <?php if ($link['type'] === $key)
                                           echo 'selected'; ?>>
                                        <?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="url" name="link_value[]" value="<?php echo htmlspecialchars($link['value']); ?>"
                                class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" 
                                placeholder="https://...">
                            <button type="button" class="remove-link p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" onclick="removeLinkRow(this)"
                                title="Entfernen">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" onclick="addLinkRow()"
                    class="mt-4 w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-[#2d7ef7] hover:text-[#2d7ef7] transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    Link hinzufügen
                </button>
            </div>
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-end">
                <a href="<?php echo htmlspecialchars(profile_url(['id' => $user['id'], 'name' => $user['name']])); ?>"
                    class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-center">
                    <i class="fas fa-times mr-2"></i>Abbrechen
                </a>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-[#2d7ef7] to-[#1e5fd9] text-white rounded-lg hover:from-[#1e5fd9] hover:to-[#0d4ab8] transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Profil speichern
                </button>
            </div>
        </form>
    </div>
</section>
    <script>
        const linkFields = {
            blog_url: "Blog",
            website_url: "Webseite",
            instagram_url: "Instagram",
            twitter_url: "Twitter/X",
            facebook_url: "Facebook",
            tiktok_url: "TikTok",
            youtube_url: "YouTube"
        };

        function addLinkRow() {
            const section = document.getElementById('links-section');
            const div = document.createElement('div');
            div.className = 'flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg link-row';
            let select = '<select name="link_type[]" class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" onchange="updateLinkTypeOptions()">';
            for (const key in linkFields) {
                select += `<option value="${key}">${linkFields[key]}</option>`;
            }
            select += '</select>';
            div.innerHTML = select +
                '<input type="url" name="link_value[]" class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" placeholder="https://...">' +
                '<button type="button" class="remove-link p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" onclick="removeLinkRow(this)" title="Entfernen"><i class="fas fa-trash"></i></button>';
            section.appendChild(div);
            updateLinkTypeOptions();
        }

        function removeLinkRow(btn) {
            btn.closest('.link-row').remove();
            updateLinkTypeOptions();
        }

        function updateLinkTypeOptions() {
            const selectedTypes = Array.from(document.querySelectorAll('select[name="link_type[]"]'))
                .map(sel => sel.value);

            document.querySelectorAll('select[name="link_type[]"]').forEach(sel => {
                const currentValue = sel.value;
                sel.innerHTML = "";
                for (const key in linkFields) {
                    if (!selectedTypes.includes(key) || key === currentValue) {
                        const opt = document.createElement("option");
                        opt.value = key;
                        opt.textContent = linkFields[key];
                        if (key === currentValue) opt.selected = true;
                        sel.appendChild(opt);
                    }
                }
            });
        }

        document.addEventListener("DOMContentLoaded", updateLinkTypeOptions);

        // Avatar Vorschau und Drag & Drop
        document.addEventListener("DOMContentLoaded", function () {
            const avatarInput = document.getElementById('avatarInput');
            const avatarImg = document.getElementById('avatarPreview');
            const uploadArea = document.getElementById('avatarUploadArea');
            const selectBtn = document.getElementById('selectAvatarBtn');
            
            if (!avatarInput || !avatarImg || !uploadArea) return;

            const originalSrc = avatarImg.getAttribute('src');
            let objectUrl = null;

            // File input change handler
            avatarInput.addEventListener('change', function () {
                const file = avatarInput.files && avatarInput.files[0];
                updateAvatarPreview(file);
            });

            // Select button click handler
            if (selectBtn) {
                selectBtn.addEventListener('click', function() {
                    avatarInput.click();
                });
            }

            // Upload area click handler
            uploadArea.addEventListener('click', function() {
                avatarInput.click();
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-[#2d7ef7]', 'bg-blue-50', 'dark:bg-blue-900/20');
            });

            uploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-[#2d7ef7]', 'bg-blue-50', 'dark:bg-blue-900/20');
            });

            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-[#2d7ef7]', 'bg-blue-50', 'dark:bg-blue-900/20');
                
                const files = Array.from(e.dataTransfer.files);
                const imageFiles = files.filter(file => file.type.startsWith('image/'));
                
                if (imageFiles.length > 0) {
                    // Create a new FileList with the dropped image
                    const dt = new DataTransfer();
                    dt.items.add(imageFiles[0]);
                    avatarInput.files = dt.files;
                    updateAvatarPreview(imageFiles[0]);
                }
            });

            function updateAvatarPreview(file) {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }

                if (file && file.type && file.type.startsWith('image/')) {
                    objectUrl = URL.createObjectURL(file);
                    avatarImg.src = objectUrl;
                } else {
                    avatarImg.src = originalSrc;
                }
            }
        });
    </script>

<?php
include __DIR__ . '/includes/footer.php';
?>