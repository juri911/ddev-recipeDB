<?php if (is_admin()): ?>
    <div class="bg-gray-100 border-b">
        <div class="container mx-auto px-4">
            <nav class="flex space-x-4 py-2">
                <a href="/admin/categories.php" 
                   class="<?= str_ends_with($_SERVER['PHP_SELF'], '/admin/categories.php') ? 'text-emerald-600' : 'text-gray-600 hover:text-gray-800' ?>">
                    Kategorien
                </a>
                <!-- Hier können weitere Admin-Links hinzugefügt werden -->
            </nav>
        </div>
    </div>
<?php endif; ?>