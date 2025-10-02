/**
 * Notification Badge Utilities
 * Helper functions to update notification badges from anywhere in the application
 */

/**
 * Refresh all notification badges after an action (mark as read, delete, etc.)
 * Call this function after any action that changes notification count
 */
function refreshNotificationBadges() {
    console.log('[NotificationUtils] Refreshing badges...');
    
    if (window.NotificationBadgeManager) {
        return window.NotificationBadgeManager.fetchAndUpdate();
    } else if (window.refreshNotificationBadges) {
        return window.refreshNotificationBadges();
    } else {
        console.warn('[NotificationUtils] No badge manager available');
        return Promise.resolve(0);
    }
}

/**
 * Update badges with a specific count (without server fetch)
 */
function updateNotificationBadgeCount(count) {
    console.log(`[NotificationUtils] Setting badge count to: ${count}`);
    
    if (window.NotificationBadgeManager) {
        window.NotificationBadgeManager.updateAllBadges(count);
    } else if (window.updateNotificationBadges) {
        window.updateNotificationBadges(count);
    } else {
        console.warn('[NotificationUtils] No badge manager available');
    }
}

/**
 * Add event listeners to forms/buttons that affect notifications
 * Usage: addNotificationRefreshListener('#mark-read-form', 'submit');
 */
function addNotificationRefreshListener(selector, eventType = 'click') {
    document.addEventListener('DOMContentLoaded', function() {
        const elements = document.querySelectorAll(selector);
        
        elements.forEach(element => {
            element.addEventListener(eventType, function(event) {
                // Add a small delay to allow the server action to complete
                setTimeout(() => {
                    refreshNotificationBadges();
                }, 500);
            });
        });
        
        console.log(`[NotificationUtils] Added refresh listeners to ${elements.length} elements`);
    });
}

/**
 * Auto-refresh badges when notification-related forms are submitted
 * This automatically detects common notification actions
 */
function autoDetectNotificationActions() {
    document.addEventListener('DOMContentLoaded', function() {
        // Common selectors for notification-related actions
        const selectors = [
            'form[action*="notification"]',
            'button[name*="mark_read"]',
            'button[name*="delete"]',
            '.notification-action',
            '[data-notification-action]'
        ];
        
        selectors.forEach(selector => {
            const elements = document.querySelectorAll(selector);
            elements.forEach(element => {
                const eventType = element.tagName.toLowerCase() === 'form' ? 'submit' : 'click';
                
                element.addEventListener(eventType, function() {
                    setTimeout(() => {
                        refreshNotificationBadges();
                    }, 500);
                });
            });
        });
        
        console.log('[NotificationUtils] Auto-detection setup complete');
    });
}

// Auto-initialize
autoDetectNotificationActions();

// Make functions globally available
window.refreshNotificationBadges = refreshNotificationBadges;
window.updateNotificationBadgeCount = updateNotificationBadgeCount;
window.addNotificationRefreshListener = addNotificationRefreshListener;
