// Global JS for RecipeHub


export function initCarousels() {
    document.querySelectorAll('[data-carousel]')?.forEach(carousel => {
        const slides = carousel.querySelectorAll('[data-slide]');
        let current = 0;
        const show = (idx) => { slides.forEach((s, i) => s.classList.toggle('hidden', i !== idx)); };
        carousel.querySelector('[data-prev]')?.addEventListener('click', () => { current = (current - 1 + slides.length) % slides.length; show(current); });
        carousel.querySelector('[data-next]')?.addEventListener('click', () => { current = (current + 1) % slides.length; show(current); });
        show(0);
    });
}

export function initDrawer(triggerId, panelId, overlayId) {
    const trigger = document.getElementById(triggerId);
    const panel = document.getElementById(panelId);
    const overlay = document.getElementById(overlayId);
    const setOpen = (open) => {
        if (!panel || !overlay || !trigger) return;
        panel.classList.toggle('open', open);
        overlay.classList.toggle('open', open);
        trigger.setAttribute('aria-expanded', String(open));
    };
    trigger?.addEventListener('click', () => setOpen(!(panel?.classList.contains('open'))));
    overlay?.addEventListener('click', () => setOpen(false));
    return { open: () => setOpen(true), close: () => setOpen(false) };
}

export function init() {
    initCarousels();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Flash Messages JavaScript - Fügen Sie dies am Ende Ihrer public/assets/js/app.js hinzu

/**
 * Flash Messages System JavaScript
 */

// Auto-Hide Timer für Flash Messages
const FLASH_AUTO_HIDE_DELAY = 5000; // 5 Sekunden

/**
 * Flash Message entfernen
 */
function removeFlashMessage(element) {
    if (!element) return;
    
    // Slide-up Animation
    element.classList.remove('animate-slide-down');
    element.classList.add('animate-slide-up');
    
    // Element nach Animation entfernen
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
        
        // Container entfernen wenn keine Messages mehr da sind
        const container = document.getElementById('flash-messages-container');
        if (container && container.children.length === 0) {
            container.remove();
        }
        
        // Repositioning der verbleibenden Messages
        repositionFlashMessages();
    }, 300);
}

/**
 * Flash Messages neu positionieren
 */
function repositionFlashMessages() {
    const messages = document.querySelectorAll('.flash-message');
    messages.forEach((msg, index) => {
        const topPosition = 5 + (index * 3.5); // 5rem + 3.5rem für jede weitere
        msg.style.top = `${topPosition}rem`;
    });
}

/**
 * Flash Message mit Auto-Hide erstellen
 */
function createFlashMessage(type, message, autoHide = true) {
    // Container erstellen falls nicht vorhanden
    let container = document.getElementById('flash-messages-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'flash-messages-container';
        container.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; z-index: 9999; pointer-events: none;';
        document.body.appendChild(container);
    }
    
    const bgClass = {
        'success': 'bg-green-500 border-green-700',
        'error': 'bg-red-500 border-red-700', 
        'warning': 'bg-yellow-500 border-yellow-700',
        'info': 'bg-blue-500 border-blue-700'
    }[type] || 'bg-gray-500 border-gray-700';
    
    const iconClass = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle', 
        'info': 'fa-info-circle'
    }[type] || 'fa-bell';
    
    // Position für neue Message berechnen
    const existingMessages = document.querySelectorAll('.flash-message');
    const topPosition = 5 + (existingMessages.length * 3.5);
    
    // Flash Message Element erstellen
    const flashElement = document.createElement('div');
    flashElement.className = `flash-message fixed left-1/2 transform -translate-x-1/2 z-50 
                              max-w-md w-full mx-4 shadow-lg rounded-lg px-6 py-4 ${bgClass} 
                              border-l-4 text-white animate-slide-down`;
    flashElement.style.top = `${topPosition}rem`;
    flashElement.setAttribute('data-flash-type', type);
    
    // Progress Bar für Auto-Hide
    let progressBar = '';
    if (autoHide) {
        progressBar = '<div class="flash-progress" style="width: 100%;"></div>';
    }
    
    flashElement.innerHTML = `
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas ${iconClass} text-xl"></i>
            </div>
            <div class="ml-3 w-0 flex-1">
                <p class="text-sm font-medium leading-5">${escapeHtml(message)}</p>
            </div>
            <div class="ml-4 flex-shrink-0 flex">
                <button class="inline-flex text-white hover:text-gray-200 
                               transition ease-in-out duration-150 focus:outline-none" 
                        onclick="removeFlashMessage(this.closest('.flash-message'))">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" 
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" 
                              clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
        ${progressBar}
    `;
    
    container.appendChild(flashElement);
    
    // Auto-Hide mit Progress Bar
    if (autoHide) {
        const progressElement = flashElement.querySelector('.flash-progress');
        if (progressElement) {
            // Progress Bar Animation
            setTimeout(() => {
                progressElement.style.width = '0%';
                progressElement.style.transition = `width ${FLASH_AUTO_HIDE_DELAY}ms linear`;
            }, 100);
        }
        
        // Auto-Remove nach Delay
        setTimeout(() => {
            if (flashElement.parentNode) {
                removeFlashMessage(flashElement);
            }
        }, FLASH_AUTO_HIDE_DELAY);
    }
    
    // Limit Messages (max 3)
    limitFlashMessages();
    
    return flashElement;
}

/**
 * Hilfsfunktionen für Flash Messages
 */
function showSuccessMessage(message, autoHide = true) {
    return createFlashMessage('success', message, autoHide);
}

function showErrorMessage(message, autoHide = true) {
    return createFlashMessage('error', message, autoHide);
}

function showInfoMessage(message, autoHide = true) {
    return createFlashMessage('info', message, autoHide);
}

function showWarningMessage(message, autoHide = true) {
    return createFlashMessage('warning', message, autoHide);
}

/**
 * HTML escaping
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Alle Flash Messages entfernen
 */
function clearAllFlashMessages() {
    const messages = document.querySelectorAll('.flash-message');
    messages.forEach(msg => removeFlashMessage(msg));
}

/**
 * Flash Messages bei Seitenwechsel entfernen
 */
window.addEventListener('beforeunload', () => {
    clearAllFlashMessages();
});

/**
 * Flash Messages Stack Management
 * Verhindert zu viele gleichzeitige Messages
 */
const MAX_FLASH_MESSAGES = 3;

function limitFlashMessages() {
    const messages = document.querySelectorAll('.flash-message');
    if (messages.length > MAX_FLASH_MESSAGES) {
        // Älteste Messages entfernen
        for (let i = 0; i < messages.length - MAX_FLASH_MESSAGES; i++) {
            removeFlashMessage(messages[i]);
        }
    }
}

/**
 * Flash Messages automatisch beim Laden der Seite ausführen
 */
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide für bereits vorhandene Flash Messages
    const existingMessages = document.querySelectorAll('.flash-message[data-flash-type]');
    existingMessages.forEach((msg, index) => {
        // Gestaffelte Auto-Hide für bereits vorhandene Messages
        setTimeout(() => {
            if (msg.parentNode) {
                removeFlashMessage(msg);
            }
        }, FLASH_AUTO_HIDE_DELAY + (index * 500)); // Gestaffelt um 500ms
    });
});


