<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/users.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

require_login();
$user = current_user();
$dbUser = get_user_by_id((int) $user['id']);

$error = '';
$success = '';
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
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil bearbeiten - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto p-4">
        <a href="javascript:history.back()" class="text-sm text-gray-500 hover:text-gray-800 flex items-center mb-4">
            &#8592; Zurück
        </a>
        <h1 class="text-xl font-semibold mb-4">Profil bearbeiten</h1>
        <?php if ($error): ?>
            <div class="text-red-600 text-sm mb-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="text-emerald-600 text-sm mb-3"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="text-emerald-700 mb-4">Profil wurde erfolgreich gelöscht.</div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="bg-white border rounded p-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="flex items-center gap-4">
                <img src="<?php echo htmlspecialchars(isset($dbUser['avatar_path']) && $dbUser['avatar_path'] ? absolute_url_from_path((string) $dbUser['avatar_path']) : SITE_URL . 'images/default_avatar.png'); ?>"
                    id="avatarPreview" class="h-16 w-16 rounded-full object-cover bg-gray-200" alt="Beispiel Avatar" />
                <div>
                    <label class="text-sm">Avatar</label>
                    <input type="file" name="avatar" id="avatarInput" accept="image/*" class="block mt-1" />
                   <?php if ($dbUser['avatar_path']): ?>
                        <button type="submit" name="delete_avatar" value="1"
                            class="mt-2 text-sm text-red-600 hover:text-red-800"
                            onclick="return confirm('Avatar wirklich löschen?')">Avatar löschen</button>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <label class="text-sm">Name</label>
                <input disabled type="text" name="name" value="<?php echo htmlspecialchars($dbUser['name']); ?>"
                    class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="text-sm">User Titel</label>
                <input name="user_titel" type="text"
                    class="mt-1 w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($dbUser['user_titel'] ?? ''); ?>"></input>
            </div>
            <div>
                <label class="text-sm">Bio</label>
                <textarea name="bio" rows="4"
                    class="mt-1 w-full border rounded px-3 py-2"><?php echo htmlspecialchars($dbUser['bio'] ?? ''); ?></textarea>
            </div>
            <div id="links-section" class="space-y-2">
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
                    <div class="flex gap-2 link-row">
                        <select name="link_type[]" class="border rounded px-3 py-2" onchange="updateLinkTypeOptions()">
                            <?php foreach ($linkFields as $key => $lbl): ?>
                                <option value="<?php echo $key; ?>" <?php if ($link['type'] === $key)
                                       echo 'selected'; ?>>
                                    <?php echo $lbl; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="url" name="link_value[]" value="<?php echo htmlspecialchars($link['value']); ?>"
                            class="w-full border rounded px-3 py-2" placeholder="Link eingeben...">
                        <button type="button" class="remove-link px-2 text-red-600" onclick="removeLinkRow(this)"
                            title="Entfernen">&times;</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" onclick="addLinkRow()"
                class="mt-2 px-4 py-2 bg-emerald-100 text-emerald-700 rounded">+ Link hinzufügen</button>
            <div class="flex gap-2">
                <a href="<?php echo htmlspecialchars(profile_url(['id' => $user['id'], 'name' => $user['name']])); ?>"
                    class="px-4 py-2 border rounded">Abbrechen</a>
                <button class="px-4 py-2 bg-emerald-600 text-white rounded">Speichern</button>
            </div>
        </form>
        <!-- Profil löschen Button + Modal bleibt wie gehabt -->
    </div>
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
            div.className = 'flex gap-2 link-row';
            let select = '<select name="link_type[]" class="border rounded px-3 py-2" onchange="updateLinkTypeOptions()">';
            for (const key in linkFields) {
                select += `<option value="${key}">${linkFields[key]}</option>`;
            }
            select += '</select>';
            div.innerHTML = select +
                '<input type="url" name="link_value[]" class="w-full border rounded px-3 py-2" placeholder="Link eingeben...">' +
                '<button type="button" class="remove-link px-2 text-red-600" onclick="removeLinkRow(this)" title="Entfernen">&times;</button>';
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

         // Avatar Vorschau bei Dateiauswahl (nur Client-Seite, keine Speicherung bis zum Absenden)
        document.addEventListener("DOMContentLoaded", function () {
            const avatarInput = document.getElementById('avatarInput');
            const avatarImg = document.getElementById('avatarPreview');
            if (!avatarInput || !avatarImg) return;

            const originalSrc = avatarImg.getAttribute('src');
            let objectUrl = null;

            avatarInput.addEventListener('change', function () {
                const file = avatarInput.files && avatarInput.files[0];

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
            });
        });
    </script>
</body>

</html>