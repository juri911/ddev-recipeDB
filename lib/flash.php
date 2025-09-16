<?php
/**
 * Flash Messages System
 * Speichert temporäre Nachrichten in der Session
 */

/**
 * Flash Message setzen
 */
function set_flash_message($type, $message) {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => time()
    ];
}

/**
 * Alle Flash Messages abrufen und löschen
 */
function get_flash_messages() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    
    return $messages;
}

/**
 * Prüfen ob Flash Messages vorhanden sind
 */
function has_flash_messages() {
    if (!isset($_SESSION)) {
        session_start();
    }
    
    return !empty($_SESSION['flash_messages']);
}

/**
 * Erfolgs-Nachricht setzen
 */
function flash_success($message) {
    set_flash_message('success', $message);
}

/**
 * Fehler-Nachricht setzen  
 */
function flash_error($message) {
    set_flash_message('error', $message);
}

/**
 * Info-Nachricht setzen
 */
function flash_info($message) {
    set_flash_message('info', $message);
}

/**
 * Warnung setzen
 */
function flash_warning($message) {
    set_flash_message('warning', $message);
}

/**
 * Flash Messages als HTML ausgeben
 */
function display_flash_messages() {
    $messages = get_flash_messages();
    if (empty($messages)) {
        return '';
    }
    
    $html = '<div id="flash-messages-container">';
    
    foreach ($messages as $flash) {
        $type = htmlspecialchars($flash['type']);
        $message = htmlspecialchars($flash['message']);
        
        $bgClass = match($type) {
            'success' => 'bg-green-500 border-green-700',
            'error' => 'bg-red-500 border-red-700',
            'warning' => 'bg-yellow-500 border-yellow-700',
            'info' => 'bg-blue-500 border-blue-700',
            default => 'bg-gray-500 border-gray-700'
        };
        
        $iconClass = match($type) {
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle', 
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle',
            default => 'fa-bell'
        };
        
        $html .= "
        <div class='flash-message fixed top-20 left-1/2 transform -translate-x-1/2 z-50 
                    max-w-md w-full mx-4 shadow-lg rounded-lg px-6 py-4 {$bgClass} 
                    border-l-4 text-white animate-slide-down' 
             data-flash-type='{$type}'>
            <div class='flex items-start'>
                <div class='flex-shrink-0'>
                    <i class='fas {$iconClass} text-xl'></i>
                </div>
                <div class='ml-3 w-0 flex-1'>
                    <p class='text-sm font-medium leading-5'>{$message}</p>
                </div>
                <div class='ml-4 flex-shrink-0 flex'>
                    <button class='inline-flex text-white hover:text-gray-200 
                                   transition ease-in-out duration-150 focus:outline-none' 
                            onclick='removeFlashMessage(this.parentElement.parentElement.parentElement)'>
                        <svg class='h-5 w-5' fill='currentColor' viewBox='0 0 20 20'>
                            <path fill-rule='evenodd' 
                                  d='M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z' 
                                  clip-rule='evenodd'/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>";
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Redirect mit Flash Message (Utility-Funktion)
 */
function redirect_with_flash($url, $type, $message) {
    set_flash_message($type, $message);
    header('Location: ' . $url);
    exit;
}
?>