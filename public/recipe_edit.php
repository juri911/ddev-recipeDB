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
$error = null;
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

$pageTitle = 'Rezept bearbeiten - ' . APP_NAME;
$csrfToken = csrf_token();

include __DIR__ . '/includes/header.php';
?>
<section class="min-h-screen w-full">
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Rezept bearbeiten</h1>
            <p class="text-gray-600 dark:text-gray-400">Bearbeite dein Rezept und teile es mit der Community</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            
            <!-- Basic Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Grundinformationen</h2>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Rezepttitel *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required 
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" 
                               placeholder="z.B. Spaghetti Carbonara" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung *</label>
                        <div class="relative">
                            <textarea name="description" required rows="4" maxlength="500"
                                      class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"
                                      placeholder="Beschreibe dein Rezept kurz..."
                                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($recipe['description']); ?></textarea>
                            <div class="absolute bottom-2 right-2 text-xs text-gray-500 dark:text-gray-400">
                                <span id="char-count"><?php echo strlen($recipe['description']); ?></span>/500 Zeichen
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Ingredients Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zutaten</h2>
                
                <div id="ingredients-container" class="space-y-3">
                    <?php foreach ($recipe['ingredients'] as $idx => $ingredient): ?>
                        <div class="flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg" data-ingredient-item>
                            <input type="number" name="ingredients[<?php echo $idx; ?>][quantity]" value="<?php echo htmlspecialchars($ingredient['quantity']); ?>" 
                                   placeholder="Menge" class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" 
                                   step="any" />
                            <select name="ingredients[<?php echo $idx; ?>][unit]" class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors">
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
                            <input type="text" name="ingredients[<?php echo $idx; ?>][name]" value="<?php echo htmlspecialchars($ingredient['name']); ?>" 
                                   placeholder="Zutat" required class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" />
                            <button type="button" class="remove-ingredient p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-ingredient" 
                        class="mt-4 w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-[#2d7ef7] hover:text-[#2d7ef7] transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    Zutat hinzufügen
                </button>
            </div>

            <!-- Steps Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zubereitungsschritte</h2>
                
                <div id="steps-container" class="space-y-4">
                    <?php foreach ($recipe['steps'] as $idx => $step): ?>
                        <div class="flex gap-3 items-start" data-step-item>
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#2d7ef7] text-white flex items-center justify-center font-semibold text-sm">
                                <?php echo $idx + 1; ?>
                            </div>
                            <div class="flex-1">
                                <textarea name="steps[<?php echo $idx; ?>][description]" rows="3" 
                                          placeholder="Beschreibe den <?php echo $idx + 1; ?>. Schritt..."
                                          required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"><?php echo htmlspecialchars($step['description']); ?></textarea>
                            </div>
                            <button type="button" class="remove-step p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="add-step" 
                        class="mt-4 w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-[#2d7ef7] hover:text-[#2d7ef7] transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    Schritt hinzufügen
                </button>
            </div>

            <!-- Additional Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zusätzliche Informationen</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Schwierigkeit</label>
                        <select name="difficulty" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors">
                            <option value="easy" <?php echo $recipe['difficulty']==='easy'?'selected':''; ?>>Leicht</option>
                            <option value="medium" <?php echo $recipe['difficulty']==='medium'?'selected':''; ?>>Mittel</option>
                            <option value="hard" <?php echo $recipe['difficulty']==='hard'?'selected':''; ?>>Schwer</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dauer (Min)</label>
                        <input type="number" name="duration_minutes" min="0" value="<?php echo (int)$recipe['duration_minutes']; ?>" 
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" 
                               placeholder="0" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Portionen</label>
                        <input type="number" name="portions" min="1" value="<?php echo (int)$recipe['portions']; ?>" 
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategorie</label>
                        <select name="category" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors">
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
                </div>
            </div>

            <!-- Images Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Bilder</h2>
                
                <div class="space-y-6">
                    <!-- Current Images -->
                    <?php if (!empty($recipe['images'])): ?>
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Aktuelle Bilder</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                                <?php foreach ($recipe['images'] as $img): ?>
                                    <div class="relative group">
                                        <div class="aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
                                            <img src="/<?php echo htmlspecialchars($img['file_path']); ?>" 
                                                 class="w-full h-full object-cover" alt="Bild" />
                                        </div>
                                        <button type="button" class="delete-image absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-all duration-200 shadow-lg" 
                                                data-image-id="<?php echo (int)$img['id']; ?>">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- New Images Upload -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Neue Bilder hinzufügen</h3>
                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-[#2d7ef7] transition-colors">
                            <input type="file" name="images[]" multiple accept="image/*" 
                                   class="hidden" id="image-upload" />
                            <label for="image-upload" class="cursor-pointer flex flex-col items-center">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                                <p class="text-gray-600 dark:text-gray-400 mb-2">Neue Bilder hochladen</p>
                                <p class="text-sm text-gray-500 dark:text-gray-500">Klicken oder Dateien hierher ziehen</p>
                            </label>
                        </div>
                        
                        <div id="image_preview_container" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mt-4"></div>
                        
                        <div class="mt-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input type="checkbox" name="replace_images" class="border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent" />
                                Vorhandene Bilder ersetzen
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-end">
                <a href="/" class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-center">
                    <i class="fas fa-times mr-2"></i>Abbrechen
                </a>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-[#2d7ef7] to-[#1e5fd9] text-white rounded-lg hover:from-[#1e5fd9] hover:to-[#0d4ab8] transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Änderungen speichern
                </button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ingredientsContainer = document.getElementById('ingredients-container');
    const stepsContainer = document.getElementById('steps-container');

    // Zutaten hinzufügen
    document.getElementById('add-ingredient').addEventListener('click', () => {
        const newIdx = ingredientsContainer.children.length;
        const ingredientDiv = document.createElement('div');
        ingredientDiv.className = 'flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg';
        ingredientDiv.setAttribute('data-ingredient-item', '');
        ingredientDiv.innerHTML = `
            <input type="number" name="ingredients[${newIdx}][quantity]" placeholder="Menge" 
                   class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" 
                   step="0.01" />
            <select name="ingredients[${newIdx}][unit]" class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors">
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
            <input type="text" name="ingredients[${newIdx}][name]" placeholder="Zutat" required 
                   class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" />
            <button type="button" class="remove-ingredient p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                <i class="fas fa-trash"></i>
            </button>
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
        stepDiv.className = 'flex gap-3 items-start';
        stepDiv.setAttribute('data-step-item', '');
        stepDiv.innerHTML = `
            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#2d7ef7] text-white flex items-center justify-center font-semibold text-sm">
                ${newIdx + 1}
            </div>
            <div class="flex-1">
                <textarea name="steps[${newIdx}][description]" rows="3" 
                          placeholder="Beschreibe den ${newIdx + 1}. Schritt..."
                          required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"></textarea>
            </div>
            <button type="button" class="remove-step p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                <i class="fas fa-trash"></i>
            </button>
        `;
        stepsContainer.appendChild(stepDiv);
    });

    // Schritte entfernen + reindex
    stepsContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-step')) {
            e.target.closest('[data-step-item]').remove();
            updateStepNumbers();
            Array.from(stepsContainer.children).forEach((child, idx) => {
                child.querySelector('textarea').name = child.querySelector('textarea').name.replace(/steps\[\d+\]/, `steps[${idx}]`);
            });
        }
    });

    // Update step numbers
    function updateStepNumbers() {
        const steps = stepsContainer.querySelectorAll('[data-step-item]');
        steps.forEach((step, index) => {
            const numberDiv = step.querySelector('.flex-shrink-0.w-8.h-8');
            if (numberDiv) {
                numberDiv.textContent = index + 1;
            }
            const textarea = step.querySelector('textarea');
            if (textarea) {
                textarea.placeholder = `Beschreibe den ${index + 1}. Schritt...`;
            }
        });
    }

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
                <div class="aspect-square rounded-lg overflow-hidden border border-gray-200 dark:border-gray-600">
                    <img src="${url}" class="w-full h-full object-cover" alt="Vorschau Bild" />
                </div>
                <button type="button" data-index="${index}" 
                        class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full h-6 w-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-all duration-200 shadow-lg">
                    <i class="fas fa-times"></i>
                </button>
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

// Zeichenzähler für Beschreibung
function updateCharCount(textarea) {
    const charCount = document.getElementById('char-count');
    if (charCount) {
        const currentLength = textarea.value.length;
        const maxLength = 500;
        charCount.textContent = currentLength;
        
        // Farbe ändern basierend auf verbleibenden Zeichen
        const remaining = maxLength - currentLength;
        if (remaining < 50) {
            charCount.parentElement.className = 'absolute bottom-2 right-2 text-xs text-red-500 dark:text-red-400';
        } else if (remaining < 100) {
            charCount.parentElement.className = 'absolute bottom-2 right-2 text-xs text-yellow-500 dark:text-yellow-400';
        } else {
            charCount.parentElement.className = 'absolute bottom-2 right-2 text-xs text-gray-500 dark:text-gray-400';
        }
    }
}
</script>

<?php
include __DIR__ . '/includes/footer.php';
?>
