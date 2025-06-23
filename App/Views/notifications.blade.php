<?php
/**
 * LazyMePHP Notification System Component
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
*/

use Core\Helpers\NotificationHelper;
?>

<!-- Notification System Component -->
<style>
/* Enhanced Notification System Styles */
.notification-container {
    position: fixed;
    z-index: 9999;
    max-width: 350px;
    pointer-events: none;
    top: 20px;
    right: 20px;
}

.notification {
    position: relative;
    padding: 16px 20px;
    padding-right: 50px; /* Make room for close button */
    margin: 8px 0;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    pointer-events: auto;
    overflow: hidden;
    transform: translateX(100%);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    font-size: 14px;
    line-height: 1.4;
}

.notification.show {
    transform: translateX(0);
}

/* Priority-based styling */
.notification-priority-1 {
    border-left-width: 3px;
    opacity: 0.8;
}

.notification-priority-2 {
    border-left-width: 4px;
}

.notification-priority-3 {
    border-left-width: 5px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.15);
}

.notification-priority-4 {
    border-left-width: 6px;
    box-shadow: 0 16px 48px rgba(0,0,0,0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
    50% { box-shadow: 0 16px 48px rgba(220, 53, 69, 0.4); }
    100% { box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
}

/* Animation variants */
.notification-slide {
    transform: translateX(100%);
}

.notification-slide.show {
    transform: translateX(0);
}

.notification-fade {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
}

.notification-fade.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.notification-bounce {
    transform: scale(0.3) translateX(100%);
}

.notification-bounce.show {
    transform: scale(1) translateX(0);
}

/* Notification types with enhanced styling */
.notification-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: #155724;
    border-left-color: #28a745;
}

.notification-error {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    color: #721c24;
    border-left-color: #dc3545;
}

.notification-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    color: #856404;
    border-left-color: #ffc107;
}

.notification-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    color: #0c5460;
    border-left-color: #17a2b8;
}

.notification-debug {
    background: linear-gradient(135deg, #e2e3e5 0%, #d6d8db 100%);
    color: #383d41;
    border-left-color: #6c757d;
    font-family: 'Courier New', monospace;
    font-size: 12px;
}

.notification-critical {
    background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
    color: #721c24;
    border-left-color: #dc3545;
    border-left-width: 6px;
    font-weight: 600;
}

/* Category badge - Enhanced styling */
.notification-category {
    position: absolute;
    top: 16px;
    right: 45px;
    background: rgba(255,255,255,0.15);
    color: inherit;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    opacity: 0.9;
    z-index: 2;
}

.notification-category:hover {
    opacity: 1;
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Category-specific styling - Enhanced */
.notification[data-category="system"] .notification-category {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.25) 0%, rgba(220, 53, 69, 0.15) 100%);
    color: #dc3545;
    border-color: rgba(220, 53, 69, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification[data-category="validation"] .notification-category {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.25) 0%, rgba(255, 193, 7, 0.15) 100%);
    color: #ffc107;
    border-color: rgba(255, 193, 7, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification[data-category="database"] .notification-category {
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.25) 0%, rgba(220, 53, 69, 0.15) 100%);
    color: #dc3545;
    border-color: rgba(220, 53, 69, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification[data-category="security"] .notification-category {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.25) 0%, rgba(255, 193, 7, 0.15) 100%);
    color: #ffc107;
    border-color: rgba(255, 193, 7, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification[data-category="api"] .notification-category {
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.25) 0%, rgba(23, 162, 184, 0.15) 100%);
    color: #17a2b8;
    border-color: rgba(23, 162, 184, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification[data-category="user"] .notification-category {
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.25) 0%, rgba(40, 167, 69, 0.15) 100%);
    color: #28a745;
    border-color: rgba(40, 167, 69, 0.3);
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.notification-title {
    font-weight: 600;
    margin-bottom: 4px;
    font-size: 15px;
    letter-spacing: -0.01em;
    padding-right: 80px; /* Make room for badge */
}

.notification-message {
    font-size: 14px;
    line-height: 1.5;
    opacity: 0.95;
    padding-right: 80px; /* Make room for badge */
}

.notification-close {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255,255,255,0.2);
    border: none;
    font-size: 16px;
    cursor: pointer;
    opacity: 0.7;
    transition: all 0.2s ease;
    color: inherit;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(5px);
}

.notification-close:hover {
    opacity: 1;
    background: rgba(255,255,255,0.3);
    transform: scale(1.1);
}

.notification-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255,255,255,0.4);
    width: 100%;
    transform: scaleX(1);
    transform-origin: left;
    transition: transform linear;
    border-radius: 0 0 12px 12px;
}

/* Responsive design */
@media (max-width: 768px) {
    .notification-container {
        max-width: calc(100vw - 40px);
        top: 10px;
        right: 20px;
        left: 20px;
    }
    
    .notification {
        margin: 6px 0;
        padding: 14px 16px;
        font-size: 13px;
    }
    
    .notification-category {
        font-size: 8px;
        padding: 2px 6px;
        top: 10px;
        right: 40px;
        letter-spacing: 0.6px;
    }
    
    .notification-title {
        font-size: 14px;
        padding-right: 60px; /* Less padding on mobile */
    }
    
    .notification-message {
        font-size: 13px;
        padding-right: 60px; /* Less padding on mobile */
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .notification {
        background: rgba(30, 30, 30, 0.9);
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .notification-success {
        background: linear-gradient(135deg, rgba(40, 167, 69, 0.2) 0%, rgba(40, 167, 69, 0.1) 100%);
        border-left-color: #28a745;
    }
    
    .notification-error {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.2) 0%, rgba(220, 53, 69, 0.1) 100%);
        border-left-color: #dc3545;
    }
    
    .notification-warning {
        background: linear-gradient(135deg, rgba(255, 193, 7, 0.2) 0%, rgba(255, 193, 7, 0.1) 100%);
        border-left-color: #ffc107;
    }
    
    .notification-info {
        background: linear-gradient(135deg, rgba(23, 162, 184, 0.2) 0%, rgba(23, 162, 184, 0.1) 100%);
        border-left-color: #17a2b8;
    }
    
    .notification-debug {
        background: linear-gradient(135deg, rgba(108, 117, 125, 0.2) 0%, rgba(108, 117, 125, 0.1) 100%);
        border-left-color: #6c757d;
    }
    
    .notification-critical {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.3) 0%, rgba(220, 53, 69, 0.2) 100%);
        border-left-color: #dc3545;
    }
}
</style>

<!-- Notification Container -->
<div id="notification-container" class="notification-container"></div>

<!-- Notification JavaScript -->
<script>
// Initialize notifications from session when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Prevent duplicate processing
    if (window.notificationsProcessed) {
        return;
    }
    window.notificationsProcessed = true;
    
    if (typeof LazyMePHP !== 'undefined' && typeof LazyMePHP.ShowNotification === 'function') {
        <?php 
        $notifications = NotificationHelper::getAndClear();
        foreach ($notifications as $notification) {
            $type = $notification['type'];
            $message = addslashes($notification['message']);
            $options = json_encode($notification['options'] ?? []);
            echo "LazyMePHP.ShowNotification('$type', '$message', $options);\n";
        }
        ?>
    } else {
        console.error('LazyMePHP not available for notification initialization');
    }
});
</script> 