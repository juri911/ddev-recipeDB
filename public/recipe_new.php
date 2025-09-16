<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

require_login();
$user = current_user();

$error = '';
csrf_start();

// Get categories for the form
$categories = get_all_categories();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate_request()) {
        $error = 'Ungültiges CSRF-Token';
    } else {
        try {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $difficulty = $_POST['difficulty'] ?? 'easy';
            $duration = (int) ($_POST['duration_minutes'] ?? 0);
            $portions = !empty($_POST['portions']) ? (int) $_POST['portions'] : null;
            $category = !empty($_POST['category']) ? trim($_POST['category']) : null;

            $ingredients = [];
            if (isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name'])) {
                foreach ($_POST['ingredient_name'] as $key => $name) {
                    $ingredients[] = [
                        'quantity' => $_POST['ingredient_quantity'][$key] ?? '',
                        'unit' => $_POST['ingredient_unit'][$key] ?? '',
                        'name' => $name
                    ];
                }
            }

            $steps = [];
            if (isset($_POST['step_description']) && is_array($_POST['step_description'])) {
                foreach ($_POST['step_description'] as $description) {
                    $steps[] = [
                        'description' => $description
                    ];
                }
            }

            if ($title === '' || $description === '' || $ingredient_quantity === '' || $ingredient_name === '' || $steps === []) {
                $error = 'Titel, Beschreibung, Zutaten und die Zubereitungsschritte sind erforderlich';
            } else {
                $recipeId = create_recipe($user['id'], $title, $description, $difficulty, $duration, $_FILES['images'] ?? [], $ingredients, $steps, $portions, $category);
                header('Location: /');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Fehler beim Speichern des Rezepts';
        }
    }
}
?><!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neues Rezept - <?php echo APP_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
    <div class="max-w-2xl mx-auto p-4">
        <h1 class="text-xl font-semibold mb-4">Neues Rezept</h1>
        <?php if ($error): ?>
            <div class="text-red-600 text-sm mb-3"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="bg-white border rounded p-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div>
                <label class="text-sm">Titel</label>
                <input type="text" name="title" required class="mt-1 w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="text-sm">Beschreibung</label>
                <textarea name="description" required rows="6" class="mt-1 w-full border rounded px-3 py-2"
                    maxlength="250" id="description_textarea" onkeyup="updateCharCount()"></textarea>
                <p class="text-sm text-gray-500 mt-1"><span id="char_count">250</span> Zeichen übrig</p>
            </div>

            <div>
                <label class="text-sm">Zutaten</label>
                <div id="ingredients_container" class="space-y-2 mt-1">
                    <div class="flex gap-2 items-center">
                        <input type="number" name="ingredient_quantity[]" require placeholder="Menge"
                            class="w-20 border rounded px-3 py-2" step="any" />
                        <select name="ingredient_unit[]" class="w-24 border rounded px-3 py-2" require>
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
                        <input type="text" name="ingredient_name[]" require placeholder="Zutat (z.B. Mehl)"
                            class="flex-1 border rounded px-3 py-2" />
                        <button type="button" onclick="removeField(this)"
                            class="text-red-500 hover:text-red-700">X</button>
                    </div>
                </div>
                <button type="button" onclick="addIngredientField()"
                    class="mt-2 px-3 py-1 border rounded bg-gray-100 hover:bg-gray-200">+ Zutat hinzufügen</button>
            </div>

            <div>
                <label class="text-sm">Zubereitungsschritte</label>
                <div id="steps_container" class="space-y-2 mt-1">
                    <div class="flex gap-2 items-center">
                        <textarea name="step_description[]" rows="3" placeholder="Schrittbeschreibung"
                            class="flex-1 border rounded px-3 py-2"></textarea>
                        <button type="button" onclick="removeField(this)"
                            class="text-red-500 hover:text-red-700">X</button>
                    </div>
                </div>
                <button type="button" onclick="addStepField()"
                    class="mt-2 px-3 py-1 border rounded bg-gray-100 hover:bg-gray-200">+ Schritt hinzufügen</button>
            </div>

            <script>
                function updateCharCount() {
                    const textarea = document.getElementById('description_textarea');
                    const charCountSpan = document.getElementById('char_count');
                    const maxLength = textarea.getAttribute('maxlength');
                    const currentLength = textarea.value.length;
                    const remaining = maxLength - currentLength;
                    charCountSpan.textContent = remaining;
                }
                // Initialize on page load
                document.addEventListener('DOMContentLoaded', updateCharCount);
                document.addEventListener('DOMContentLoaded', () => {
                    // Ensure at least one ingredient and step field is present on load
                    if (document.querySelectorAll('#ingredients_container .flex').length === 0) {
                        addIngredientField();
                    }
                    if (document.querySelectorAll('#steps_container .flex').length === 0) {
                        addStepField();
                    }
                });

                function addIngredientField() {
                    const container = document.getElementById('ingredients_container');
                    const newField = document.createElement('div');
                    newField.className = 'flex gap-2 items-center';
                    newField.innerHTML = `
                        <input type="number" name="ingredient_quantity[]" placeholder="Menge" class="w-20 border rounded px-3 py-2" step="any" />
                        <select name="ingredient_unit[]" class="w-24 border rounded px-3 py-2">
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
                        <input type="text" name="ingredient_name[]" placeholder="Zutat (z.B. Mehl)" class="flex-1 border rounded px-3 py-2" />
                        <button type="button" onclick="removeField(this)" class="text-red-500 hover:text-red-700">X</button>
                    `;
                    container.appendChild(newField);
                }

                function addStepField() {
                    const container = document.getElementById('steps_container');
                    const newField = document.createElement('div');
                    newField.className = 'flex gap-2 items-center';
                    newField.innerHTML = `
                        <textarea name="step_description[]" rows="3" placeholder="Schrittbeschreibung" class="flex-1 border rounded px-3 py-2"></textarea>
                        <button type="button" onclick="removeField(this)" class="text-red-500 hover:text-red-700">X</button>
                    `;
                    container.appendChild(newField);
                }

                function removeField(button) {
                    button.parentNode.remove();
                }
            </script>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="text-sm">Schwierigkeit</label>
                    <select name="difficulty" class="mt-1 w-full border rounded px-3 py-2">
                        <option value="easy">Leicht</option>
                        <option value="medium">Mittel</option>
                        <option value="hard">Schwer</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm">Dauer (Min)</label>
                    <input type="number"  name="duration_minutes" min="0" value="0"
                        class="mt-1 w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="text-sm">Portionen</label>
                    <input type="number" name="portions" min="1" placeholder="z.B. 4"
                        class="mt-1 w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="text-sm">Kategorie</label>
                    <select name="category" class="mt-1 w-full border rounded px-3 py-2">
                        <option value="">Kategorie wählen</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-sm">Bilder</label>
                <input type="file" name="images[]" multiple accept="image/*" class="mt-1 w-full" />
                 <div id="image_preview_container" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-2"></div>
            </div>
             <script>
                document.addEventListener('DOMContentLoaded', () => {
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
            <div class="flex gap-2">
                <a href="/" class="px-4 py-2 border rounded">Abbrechen</a>
                <button class="px-4 py-2 bg-emerald-600 text-white rounded">Speichern</button>
            </div>
        </form>
    </div>
</body>

</html>