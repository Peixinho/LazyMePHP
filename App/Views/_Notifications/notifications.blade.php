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

/* Close button styling */
.notification-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255,255,255,0.2);
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    color: inherit;
    transition: all 0.2s ease;
    backdrop-filter: blur(8px);
    z-index: 3;
}

.notification-close:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.1);
}

.notification-close:active {
    transform: scale(0.95);
}

/* Progress bar for auto-dismiss */
.notification-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    background: rgba(255,255,255,0.3);
    transition: width linear;
}

/* Responsive design */
@media (max-width: 768px) {
    .notification-container {
        max-width: calc(100vw - 40px);
        right: 20px;
        left: 20px;
    }
    
    .notification {
        font-size: 13px;
        padding: 14px 18px;
        padding-right: 45px;
    }
    
    .notification-category {
        font-size: 8px;
        padding: 2px 6px;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .notification {
        background: rgba(30, 30, 30, 0.95);
        border-color: rgba(255,255,255,0.1);
        color: #ffffff;
    }
    
    .notification-close {
        background: rgba(255,255,255,0.1);
        color: #ffffff;
    }
    
    .notification-close:hover {
        background: rgba(255,255,255,0.2);
    }
}
</style>

<!-- Notification Container -->
<div id="notification-container" class="notification-container">
    <!-- Notifications will be dynamically inserted here -->
</div>

<!-- Notification Template (hidden) -->
<template id="notification-template">
    <div class="notification" data-id="" data-category="" data-priority="">
        <div class="notification-category"></div>
        <div class="notification-content"></div>
        <button class="notification-close" onclick="LazyMePHP.closeNotification(this.parentElement.dataset.id)">×</button>
        <div class="notification-progress"></div>
    </div>
</template>

<script>
// Notification System JavaScript
(function() {
    'use strict';
    
    // Initialize notification system when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Check for existing notifications in session storage
        const storedNotifications = sessionStorage.getItem('lazymephp_notifications');
        if (storedNotifications) {
            try {
                const notifications = JSON.parse(storedNotifications);
                notifications.forEach(notification => {
                    showNotification(notification.message, notification.type, notification.category, notification.priority, notification.duration);
                });
                sessionStorage.removeItem('lazymephp_notifications');
            } catch (e) {
                console.error('Failed to restore notifications:', e);
            }
        }
        
        @foreach(\Core\Helpers\NotificationHelper::getAndClear() as $notification)
            @php
                $type = $notification['type'] ?? 'info';
                $duration = in_array($type, ['error', 'warning', 'critical']) ? 8000 : 5000;
            @endphp
            showNotification(
                {!! json_encode($notification['message'] ?? 'Default Message') !!},
                '{{ $type }}',
                '{{ $notification['category'] ?? 'system' }}',
                {{ $notification['priority'] ?? 1 }},
                {{ $duration }}
            );
        @endforeach
    });
    
    // Global notification function
    window.showNotification = function(message, type = 'info', category = 'system', priority = 1, duration = 5000) {
        const container = document.getElementById('notification-container');
        const template = document.getElementById('notification-template');
        
        if (!container || !template) {
            console.error('Notification system not initialized');
            return;
        }
        
        // Clone the template
        const notification = template.content.cloneNode(true);
        const notificationElement = notification.querySelector('.notification');
        
        // Generate unique ID
        const id = 'notification_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        // Set attributes
        notificationElement.dataset.id = id;
        notificationElement.dataset.category = category;
        notificationElement.dataset.priority = priority;
        
        // Add classes
        notificationElement.classList.add('notification-' + type);
        notificationElement.classList.add('notification-priority-' + priority);
        notificationElement.classList.add('notification-slide');
        
        // Set content
        notificationElement.querySelector('.notification-category').textContent = category;
        notificationElement.querySelector('.notification-content').textContent = message;
        
        // Add to container
        container.appendChild(notificationElement);
        
        // Trigger animation
        setTimeout(() => {
            notificationElement.classList.add('show');
        }, 10);
        
        // Auto-dismiss
        if (duration > 0) {
            const progressBar = notificationElement.querySelector('.notification-progress');
            if (progressBar) {
                progressBar.style.transition = `width ${duration}ms linear`;
                setTimeout(() => {
                    progressBar.style.width = '0%';
                }, 10);
            }
            
            setTimeout(() => {
                closeNotification(id);
            }, duration);
        }
        
        return id;
    };
    
    // Close notification function
    window.closeNotification = function(id) {
        const notification = document.querySelector(`[data-id="${id}"]`);
        if (notification) {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }
    };
    
    // Close all notifications
    window.closeAllNotifications = function() {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            closeNotification(notification.dataset.id);
        });
    };
    
    // Add to LazyMePHP global object if it exists
    if (typeof LazyMePHP !== 'undefined') {
        LazyMePHP.showNotification = window.showNotification;
        LazyMePHP.closeNotification = window.closeNotification;
        LazyMePHP.closeAllNotifications = window.closeAllNotifications;
    }
})();
</script> 