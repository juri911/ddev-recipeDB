/**
 * Theme Manager - Handles light/dark mode switching
 */
class ThemeManager {
    constructor() {
        this.theme = this.getStoredTheme() || this.getSystemTheme();
        this.init();
    }

    init() {
        this.applyTheme(this.theme);
        this.createThemeToggle();
        this.setupEventListeners();
    }

    getStoredTheme() {
        return localStorage.getItem('theme');
    }

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.saveTheme(theme);
    }

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
    }

    toggleTheme() {
        this.theme = this.theme === 'light' ? 'dark' : 'light';
        this.applyTheme(this.theme);
        this.updateThemeToggle(this.theme);
    }

    createThemeToggle() {
        // Find existing theme toggle or create one
        let toggleButton = document.getElementById('theme-toggle');
        
        if (!toggleButton) {
            // Create theme toggle button
            toggleButton = document.createElement('button');
            toggleButton.id = 'theme-toggle';
            toggleButton.className = 'theme-toggle bg-transparent lg:bg-[#2d7ef7] border-0 lg:border-1 border-[#2d7ef7] hover:border-[var(--rh-text)] hover:text-[var(--rh-text)] hover:bg-transparent text-white p-4 lg:py-1 lg:px-3 aspect-square lg:aspect-auto rounded-none lg:rounded transition duration-600';
            toggleButton.setAttribute('aria-label', 'Theme wechseln');
            toggleButton.setAttribute('title', 'Theme wechseln');
            
            // Insert before the user menu or at the end of navigation
            const userMenu = document.querySelector('#userMenuButton');
            const navContainer = document.querySelector('.flex.items-center.gap-x-0');
            
            if (userMenu && userMenu.parentElement) {
                userMenu.parentElement.insertBefore(toggleButton, userMenu);
            } else if (navContainer) {
                navContainer.appendChild(toggleButton);
            }
        }
        
        this.updateThemeToggle(this.theme);
    }

    updateThemeToggle(theme) {
        const toggleButton = document.getElementById('theme-toggle');
        if (toggleButton) {
            const icon = toggleButton.querySelector('i');
            const text = toggleButton.querySelector('span');
            
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun lg:text-base text-2xl' : 'fas fa-moon lg:text-base text-2xl';
            }
            
            if (text) {
                text.textContent = theme === 'dark' ? 'Light' : 'Dark';
            } else {
                // Create text span if it doesn't exist
                const textSpan = document.createElement('span');
                textSpan.className = 'hidden lg:inline ml-2';
                textSpan.textContent = theme === 'dark' ? 'Light' : 'Dark';
                toggleButton.appendChild(textSpan);
            }
        }
    }

    setupEventListeners() {
        // Theme toggle button click
        document.addEventListener('click', (e) => {
            if (e.target.closest('#theme-toggle')) {
                e.preventDefault();
                this.toggleTheme();
            }
        });

        // Keyboard shortcut (Ctrl+Shift+T)
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'T') {
                e.preventDefault();
                this.toggleTheme();
            }
        });

        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!this.getStoredTheme()) {
                this.theme = e.matches ? 'dark' : 'light';
                this.applyTheme(this.theme);
                this.updateThemeToggle(this.theme);
            }
        });
    }

    getCurrentTheme() {
        return this.theme;
    }

    setTheme(theme) {
        if (theme === 'light' || theme === 'dark') {
            this.theme = theme;
            this.applyTheme(theme);
            this.updateThemeToggle(theme);
        }
    }

    resetTheme() {
        localStorage.removeItem('theme');
        this.theme = this.getSystemTheme();
        this.applyTheme(this.theme);
        this.updateThemeToggle(this.theme);
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    window.themeManager = new ThemeManager();
});

// Make ThemeManager available globally
window.ThemeManager = ThemeManager;
