<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

require_login();
$user = current_user();

$id = (int)($_GET['id'] ?? 0);
$recipe = get_recipe_by_id($id);
if (!$recipe || (int)$recipe['user_id'] !== (int)$user['id']) {
	header('Location: /');
	exit;
}

$categories = get_all_categories(); // Kategorien für Dropdown
$error = '';
csrf_start();

$portions = !empty($_POST['portions']) ? (int)$_POST['portions'] : null;
$category = !empty($_POST['category']) ? trim($_POST['category']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_validate_request()) {
		$error = 'Ungültiges CSRF-Token';
	} else {
		try {
			$title = trim($_POST['title'] ?? '');
			$description = trim($_POST['description'] ?? '');
			$difficulty = $_POST['difficulty'] ?? 'easy';
			$duration = (int)($_POST['duration_minutes'] ?? 0);
			$portions = !empty($_POST['portions']) ? (int)$_POST['portions'] : null;
			$category = !empty($_POST['category']) ? trim($_POST['category']) : null;
			$replace = isset($_POST['replace_images']);
			$ingredients = $_POST['ingredients'] ?? [];
			$steps = $_POST['steps'] ?? [];

			// Validierung
			if ($title === '' || $description === '' || $ingredient_quantity === '' || $ingredient_name === '' || $steps === [])  {
				$error = 'Titel, Beschreibung, Zutaten und die Zubereitungsschritte sind erforderlich';
			} else {
				update_recipe(
					$id, 
					$user['id'], 
					$title, 
					$description, 
					$difficulty, 
					$duration, 
					$_FILES['images'] ?? [], 
					$replace, 
					$ingredients, 
					$steps, 
					$portions, 
					$category
				);
				header('Location: /');
				exit;
			}
		} catch (Throwable $e) {
			$error = 'Fehler beim Speichern des Rezepts';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rezept bearbeiten - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-xl font-semibold mb-4">Rezept bearbeiten</h1>

    <?php if ($error): ?>
        <div class="text-red-600 text-sm mb-3"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="bg-white border rounded p-4 space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

        <div>
            <label class="text-sm">Titel</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required class="mt-1 w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="text-sm">Beschreibung</label>
            <textarea name="description" required rows="6" class="mt-1 w-full border rounded px-3 py-2"><?php echo htmlspecialchars($recipe['description']); ?></textarea>
        </div>

         <div>
        <label class="text-sm">Kategorie</label>
        <select name="category" class="mt-1 w-full border rounded px-3 py-2">
            <option value="">Kategorie wählen</option>
            <?php 
            $categories = get_all_categories();
            foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                    <?php echo ($recipe['category'] ?? '') === $cat['name'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

        <div>
            <label class="text-sm">Portionen</label>
            <input type="number" name="portions" min="1" value="<?php echo (int)$recipe['portions']; ?>" class="mt-1 w-full border rounded px-3 py-2" />
        </div>

        <div>
            <label class="text-sm">Zutaten</label>
            <div id="ingredients-container" class="space-y-2 mt-1">
                <?php foreach ($recipe['ingredients'] as $idx => $ingredient): ?>
                    <div class="flex gap-2 items-center" data-ingredient-item>
                        <input type="number" name="ingredients[<?php echo $idx; ?>][quantity]" value="<?php echo htmlspecialchars($ingredient['quantity']); ?>" placeholder="Menge" class="w-24 border rounded px-3 py-2 text-sm" step="any" />
                        <select name="ingredients[<?php echo $idx; ?>][unit]" class="w-24 border rounded px-3 py-2 text-sm">
                            <option value="">Einheit</option>
                            <option value="g" <?php echo $ingredient['unit']==='g'?'selected':''; ?>>g</option>
                            <option value="kg" <?php echo $ingredient['unit']==='kg'?'selected':''; ?>>kg</option>
                            <option value="ml" <?php echo $ingredient['unit']==='ml'?'selected':''; ?>>ml</option>
                            <option value="l" <?php echo $ingredient['unit']==='l'?'selected':''; ?>>l</option>
                            <option value="TL" <?php echo $ingredient['unit']==='TL'?'selected':''; ?>>TL</option>
                            <option value="EL" <?php echo $ingredient['unit']==='EL'?'selected':''; ?>>EL</option>
                            <option value="Prise" <?php echo $ingredient['unit']==='Prise'?'selected':''; ?>>Prise</option>
                            <option value="Stück" <?php echo $ingredient['unit']==='Stück'?'selected':''; ?>>Stück</option>
                        </select>
                        <input type="text" name="ingredients[<?php echo $idx; ?>][name]" value="<?php echo htmlspecialchars($ingredient['name']); ?>" placeholder="Zutat" required class="flex-1 border rounded px-3 py-2 text-sm" />
                        <button type="button" class="remove-ingredient text-red-600 text-sm">Entfernen</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-ingredient" class="mt-2 px-3 py-1 bg-gray-200 text-gray-800 rounded text-sm">Zutat hinzufügen</button>
        </div>

        <div>
            <label class="text-sm">Zubereitungsschritte</label>
            <div id="steps-container" class="space-y-2 mt-1">
                <?php foreach ($recipe['steps'] as $idx => $step): ?>
                    <div class="flex gap-2 items-start" data-step-item>
                        <textarea name="steps[<?php echo $idx; ?>][description]" rows="3" placeholder="Schritt Beschreibung" required class="flex-1 border rounded px-3 py-2 text-sm"><?php echo htmlspecialchars($step['description']); ?></textarea>
                        <button type="button" class="remove-step text-red-600 text-sm">Entfernen</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-step" class="mt-2 px-3 py-1 bg-gray-200 text-gray-800 rounded text-sm">Schritt hinzufügen</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="text-sm">Schwierigkeit</label>
                <select name="difficulty" class="mt-1 w-full border rounded px-3 py-2">
                    <option value="easy" <?php echo $recipe['difficulty']==='easy'?'selected':''; ?>>leicht</option>
                    <option value="medium" <?php echo $recipe['difficulty']==='medium'?'selected':''; ?>>mittel</option>
                    <option value="hard" <?php echo $recipe['difficulty']==='hard'?'selected':''; ?>>schwer</option>
                </select>
            </div>
            <div>
                <label class="text-sm">Dauer (Min)</label>
                <input type="number" name="duration_minutes" min="0" value="<?php echo (int)$recipe['duration_minutes']; ?>" class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="text-sm">Neue Bilder</label>
                <input type="file" name="images[]" multiple accept="image/*" class="mt-1 w-full" />
                   <div id="image_preview_container" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-2"></div>
                <label class="mt-2 inline-flex items-center gap-2 text-sm"><input type="checkbox" name="replace_images" class="border rounded" /> Vorhandene Bilder ersetzen</label>
            </div>
        </div>

        <div>
            <div class="text-sm mb-1">Aktuelle Bilder</div>
            <div class="grid grid-cols-3 gap-2">
                <?php foreach ($recipe['images'] as $img): ?>
                    <div class="relative group">
                        <img src="/<?php echo htmlspecialchars($img['file_path']); ?>" class="w-full h-24 object-cover rounded border" alt="Bild" />
                        <button type="button" class="delete-image absolute top-1 right-1 bg-red-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity" data-image-id="<?php echo (int)$img['id']; ?>">
                            &times;
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="/" class="px-4 py-2 border rounded">Abbrechen</a>
            <button class="px-4 py-2 bg-emerald-600 text-white rounded">Speichern</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ingredientsContainer = document.getElementById('ingredients-container');
    const stepsContainer = document.getElementById('steps-container');

    // Zutaten hinzufügen
    document.getElementById('add-ingredient').addEventListener('click', () => {
        const newIdx = ingredientsContainer.children.length;
        const ingredientDiv = document.createElement('div');
        ingredientDiv.className = 'flex gap-2 items-center';
        ingredientDiv.setAttribute('data-ingredient-item', '');
        ingredientDiv.innerHTML = `
            <input type="number" name="ingredients[${newIdx}][quantity]" placeholder="Menge" class="w-24 border rounded px-3 py-2 text-sm" step="0.01" />
            <select name="ingredients[${newIdx}][unit]" class="w-24 border rounded px-3 py-2 text-sm">
                <option value="">Einheit</option>
                <option value="g">g</option>
                <option value="kg">kg</option>
                <option value="ml">ml</option>
                <option value="l">l</option>
                <option value="TL">TL</option>
                <option value="EL">EL</option>
                <option value="Prise">Prise</option>
                <option value="Stück">Stück</option>
            </select>
            <input type="text" name="ingredients[${newIdx}][name]" placeholder="Zutat" required class="flex-1 border rounded px-3 py-2 text-sm" />
            <button type="button" class="remove-ingredient text-red-600 text-sm">Entfernen</button>
        `;
        ingredientsContainer.appendChild(ingredientDiv);
    });

    // Zutaten entfernen + reindex
    ingredientsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-ingredient')) {
            e.target.closest('[data-ingredient-item]').remove();
            Array.from(ingredientsContainer.children).forEach((child, idx) => {
                child.querySelectorAll('input, select').forEach(input => {
                    input.name = input.name.replace(/ingredients\[\d+\]/, `ingredients[${idx}]`);
                });
            });
        }
    });

    // Schritte hinzufügen
    document.getElementById('add-step').addEventListener('click', () => {
        const newIdx = stepsContainer.children.length;
        const stepDiv = document.createElement('div');
        stepDiv.className = 'flex gap-2 items-start';
        stepDiv.setAttribute('data-step-item', '');
        stepDiv.innerHTML = `
            <textarea name="steps[${newIdx}][description]" rows="3" placeholder="Schritt Beschreibung" required class="flex-1 border rounded px-3 py-2 text-sm"></textarea>
            <button type="button" class="remove-step text-red-600 text-sm">Entfernen</button>
        `;
        stepsContainer.appendChild(stepDiv);
    });

    // Schritte entfernen + reindex
    stepsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-step')) {
            e.target.closest('[data-step-item]').remove();
            Array.from(stepsContainer.children).forEach((child, idx) => {
                child.querySelector('textarea').name = child.querySelector('textarea').name.replace(/steps\[\d+\]/, `steps[${idx}]`);
            });
        }
    });

    // Bild löschen
    document.querySelectorAll('.delete-image').forEach(button => {
        button.addEventListener('click', async (e) => {
            const imageId = e.target.dataset.imageId;
            if (confirm('Dieses Bild wirklich löschen?')) {
                try {
                    const response = await fetch('/api/delete_image.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ image_id: imageId, csrf_token: document.querySelector('input[name="csrf_token"]').value })
                    });
                    const result = await response.json();
                    if (result.ok) {
                        e.target.closest('.group').remove();
                    } else {
                        alert('Fehler beim Löschen des Bildes: ' + result.error);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Netzwerkfehler beim Löschen des Bildes.');
                }
            }
        });
    });

     // Vorschau für neu ausgewählte Bilder (nur temporär, vor dem Speichern)
    const imageInput = document.querySelector('input[name="images[]"]');
    const previewContainer = document.getElementById('image_preview_container');

    function renderImagePreviews() {
        if (!imageInput || !previewContainer) return;
        previewContainer.innerHTML = '';
        const files = Array.from(imageInput.files || []);
        files.forEach((file, index) => {
            if (!file.type.startsWith('image/')) return;
            const url = URL.createObjectURL(file);
            const wrapper = document.createElement('div');
            wrapper.className = 'relative group';
            wrapper.innerHTML = `
                <img src="${url}" class="w-full h-24 object-cover rounded border" alt="Vorschau Bild" />
                <button type="button" data-index="${index}" class="absolute top-1 right-1 bg-red-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity">&times;</button>
            `;
            previewContainer.appendChild(wrapper);
        });
    }

    function removeFileAt(indexToRemove) {
        if (!imageInput || typeof indexToRemove !== 'number') return;
        const dt = new DataTransfer();
        Array.from(imageInput.files).forEach((file, idx) => {
            if (idx !== indexToRemove) dt.items.add(file);
        });
        imageInput.files = dt.files;
        renderImagePreviews();
    }

    if (imageInput) {
        imageInput.addEventListener('change', renderImagePreviews);
    }

    if (previewContainer) {
        previewContainer.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.matches('button[data-index]')) {
                const idx = parseInt(target.getAttribute('data-index'), 10);
                if (!Number.isNaN(idx)) removeFileAt(idx);
            }
        });
    }
});
</script>
</body>
</html>
