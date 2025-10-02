# Notification Badge Update System - Usage Examples

## 🎯 Übersicht

Das globale Badge-Update-System aktualisiert **automatisch** alle Notification-Badges auf der gesamten Website, egal wo Sie sich befinden.

## ✅ Automatische Updates

Das System erkennt automatisch diese Aktionen und aktualisiert die Badges:

- Formulare mit `action*="notification"`
- Buttons mit `name*="mark_read"`
- Buttons mit `name*="delete"`
- Elemente mit Klasse `.notification-action`
- Elemente mit Attribut `data-notification-action`

## 🔧 Manuelle Verwendung

### 1. Nach einer Aktion die Badges aktualisieren:

```javascript
// Nach dem Markieren als gelesen
document.getElementById('mark-read-btn').addEventListener('click', function() {
    // Ihre Aktion hier...
    
    // Badges aktualisieren
    refreshNotificationBadges();
});
```

### 2. Badge-Count direkt setzen:

```javascript
// Badge auf 0 setzen (versteckt alle Badges)
updateNotificationBadgeCount(0);

// Badge auf 5 setzen
updateNotificationBadgeCount(5);
```

### 3. Event-Listener automatisch hinzufügen:

```javascript
// Alle Buttons mit Klasse 'mark-read' überwachen
addNotificationRefreshListener('.mark-read', 'click');

// Alle Formulare mit ID 'notification-form' überwachen
addNotificationRefreshListener('#notification-form', 'submit');
```

## 📝 HTML-Beispiele

### Automatisch erkannte Buttons:

```html
<!-- Wird automatisch erkannt -->
<button name="mark_read" class="btn">Als gelesen markieren</button>

<!-- Wird automatisch erkannt -->
<button class="notification-action" data-action="delete">Löschen</button>

<!-- Wird automatisch erkannt -->
<form action="/notifications/mark-read" method="POST">
    <button type="submit">Alle als gelesen markieren</button>
</form>
```

### Manuelle Kennzeichnung:

```html
<!-- Manuell kennzeichnen -->
<button data-notification-action="read" onclick="markAsRead(); refreshNotificationBadges();">
    Markieren
</button>
```

## 🎨 CSS-Klassen für Badges

Alle Elemente mit der Klasse `.notification-badge` werden automatisch gefunden und aktualisiert:

```html
<!-- Desktop Header -->
<span class="notification-badge" id="notification-badge-desktop">3</span>

<!-- Mobile Header -->
<span class="notification-badge" id="notification-badge-mobile">3</span>

<!-- Beliebige andere Badges -->
<span class="notification-badge">3</span>
```

## 🔄 Automatische Updates

Das System aktualisiert die Badges automatisch:

- **Beim Laden der Seite**: Sofortige Aktualisierung
- **Alle 30 Sekunden**: Periodische Updates
- **Nach Aktionen**: Wenn erkannte Buttons/Formulare verwendet werden

## 🐛 Debugging

Öffnen Sie die Browser-Konsole, um die Badge-Updates zu verfolgen:

```
[BadgeManager] Initializing...
[BadgeManager] Updating all badges with count: 3
[BadgeManager] Found 2 badges to update
[BadgeManager] Updating badge 1: notification-badge-desktop
[BadgeManager] Updating badge 2: notification-badge-mobile
```

## 💡 Tipps

1. **Keine Duplikate**: Das System verhindert doppelte Updates
2. **Fehlerbehandlung**: Fallback-Methoden für Kompatibilität
3. **Performance**: Effiziente Selektoren und Caching
4. **Synchron**: Alle Badges werden gleichzeitig aktualisiert

## 🚀 Erweiterte Verwendung

### Custom Event System:

```javascript
// Custom Event senden
document.dispatchEvent(new CustomEvent('notificationChanged', {
    detail: { count: 0 }
}));

// Custom Event empfangen
document.addEventListener('notificationChanged', function(event) {
    updateNotificationBadgeCount(event.detail.count);
});
```

### AJAX-Integration:

```javascript
fetch('/api/mark-notification-read', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        // Badges automatisch aktualisieren
        refreshNotificationBadges();
    }
});
```
