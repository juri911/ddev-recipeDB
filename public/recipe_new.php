<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/recipes.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../config.php';

require_login();
$user = current_user();

$error = null;
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

$pageTitle = 'Neues Rezept - ' . APP_NAME;
$csrfToken = csrf_token();

include __DIR__ . '/includes/header.php';
?>
<section class="min-h-screen w-full">
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Neues Rezept erstellen</h1>
            <p class="text-gray-600 dark:text-gray-400">Teile dein Lieblingsrezept mit der Community</p>
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
                        <input type="text" name="title" required 
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" 
                               placeholder="z.B. Spaghetti Carbonara" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Beschreibung *</label>
                        <textarea name="description" required rows="4" 
                                  class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"
                                  maxlength="500" id="description_textarea" onkeyup="updateCharCount()"
                                  placeholder="Beschreibe dein Rezept kurz..."></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <span id="char_count">500</span> Zeichen übrig
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ingredients Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zutaten</h2>
                
                <div id="ingredients_container" class="space-y-3">
                    <div class="flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <input type="number" name="ingredient_quantity[]" required placeholder="Menge"
                            class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" 
                            step="any" />
                        <select name="ingredient_unit[]" class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" required>
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
                        <input type="text" name="ingredient_name[]" required placeholder="Zutat (z.B. Mehl)"
                            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" />
                        <button type="button" onclick="removeField(this)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <button type="button" onclick="addIngredientField()"
                    class="mt-4 w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-[#2d7ef7] hover:text-[#2d7ef7] transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    Zutat hinzufügen
                </button>
            </div>

            <!-- Steps Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zubereitungsschritte</h2>
                
                <div id="steps_container" class="space-y-4">
                    <div class="flex gap-3 items-start">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#2d7ef7] text-white flex items-center justify-center font-semibold text-sm">
                            1
                        </div>
                        <div class="flex-1">
                            <textarea name="step_description[]" rows="3" placeholder="Beschreibe den ersten Schritt..."
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"></textarea>
                        </div>
                        <button type="button" onclick="removeField(this)"
                            class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <button type="button" onclick="addStepField()"
                    class="mt-4 w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-gray-600 dark:text-gray-400 hover:border-[#2d7ef7] hover:text-[#2d7ef7] transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-plus"></i>
                    Schritt hinzufügen
                </button>
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
                    newField.className = 'flex gap-3 items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg';
                    newField.innerHTML = `
                        <input type="number" name="ingredient_quantity[]" placeholder="Menge" 
                               class="w-20 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" 
                               step="any" />
                        <select name="ingredient_unit[]" class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors">
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
                        <input type="text" name="ingredient_name[]" placeholder="Zutat (z.B. Mehl)" 
                               class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-600 dark:text-white transition-colors" />
                        <button type="button" onclick="removeField(this)" 
                                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    container.appendChild(newField);
                }

                function addStepField() {
                    const container = document.getElementById('steps_container');
                    const stepNumber = container.children.length + 1;
                    const newField = document.createElement('div');
                    newField.className = 'flex gap-3 items-start';
                    newField.innerHTML = `
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#2d7ef7] text-white flex items-center justify-center font-semibold text-sm">
                            ${stepNumber}
                        </div>
                        <div class="flex-1">
                            <textarea name="step_description[]" rows="3" placeholder="Beschreibe den ${stepNumber}. Schritt..."
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors resize-none"></textarea>
                        </div>
                        <button type="button" onclick="removeField(this)" 
                                class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                    container.appendChild(newField);
                }

                function removeField(button) {
                    button.parentNode.remove();
                    // Update step numbers after removal
                    updateStepNumbers();
                }

                function updateStepNumbers() {
                    const stepsContainer = document.getElementById('steps_container');
                    const steps = stepsContainer.querySelectorAll('.flex.gap-3.items-start');
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
            </script>
            <!-- Additional Information Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-lg border border-gray-200 dark:border-gray-600">
                <h2 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">Zusätzliche Informationen</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Schwierigkeit</label>
                        <select name="difficulty" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors">
                            <option value="easy">Leicht</option>
                            <option value="medium">Mittel</option>
                            <option value="hard">Schwer</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dauer (Min)</label>
                        <input type="number" name="duration_minutes" min="0" value="0"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" 
                            placeholder="0" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Portionen</label>
                        <input type="number" name="portions" min="1" placeholder="z.B. 4"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategorie</label>
                        <select name="category" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#2d7ef7] focus:border-transparent dark:bg-gray-700 dark:text-white transition-colors">
                            <option value="">Kategorie wählen</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>">
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
                
                <div class="space-y-4">
                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-[#2d7ef7] transition-colors">
                        <input type="file" name="images[]" multiple accept="image/*" 
                               class="hidden" id="image-upload" />
                        <label for="image-upload" class="cursor-pointer flex flex-col items-center">
                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 dark:text-gray-400 mb-2">Bilder hochladen</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500">Klicken oder Dateien hierher ziehen</p>
                        </label>
                    </div>
                    
                    <div id="image_preview_container" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4"></div>
                </div>
            </div>
             <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const imageInput = document.querySelector('input[name="images[]"]');
                    const previewContainer = document.getElementById('image_preview_container');
                    const uploadArea = document.querySelector('.border-2.border-dashed');

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

                    // Drag and drop functionality
                    if (uploadArea) {
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
                                const dt = new DataTransfer();
                                Array.from(imageInput.files).forEach(file => dt.items.add(file));
                                imageFiles.forEach(file => dt.items.add(file));
                                imageInput.files = dt.files;
                                renderImagePreviews();
                            }
                        });
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
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-end">
                <a href="/" class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-center">
                    <i class="fas fa-times mr-2"></i>Abbrechen
                </a>
                <button type="submit" class="px-6 py-3 bg-gradient-to-r from-[#2d7ef7] to-[#1e5fd9] text-white rounded-lg hover:from-[#1e5fd9] hover:to-[#0d4ab8] transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-save mr-2"></i>Rezept speichern
                </button>
            </div>
        </form>
    </div>
</section>

<?php
include __DIR__ . '/includes/footer.php';
?>