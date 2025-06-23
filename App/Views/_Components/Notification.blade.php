<?php

/**
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 *
 * $type (string) - 'success', 'error', 'warning', 'info'
 * $message (string) - The message to display
 * $title (string) - Optional title for the notification
 * $duration (int) - Auto-dismiss duration in milliseconds (default: 5000)
 * $dismissible (bool) - Whether the notification can be dismissed (default: true)
 * $position (string) - Position: 'top-right', 'top-left', 'bottom-right', 'bottom-left' (default: 'top-right')
 * $animation (string) - Animation: 'slide', 'fade', 'bounce' (default: 'slide')
 */
?>

<div id="notification-container-{{$type or 'default'}}" 
     class="notification-container notification-{{$position or 'top-right'}}"
     style="display: none;">
    
    <div class="notification notification-{{$type or 'info'}} notification-{{$animation or 'slide'}}"
         data-duration="{{$duration or 5000}}"
         data-dismissible="{{$dismissible or 'true'}}">
        
        @if(isset($title))
            <div class="notification-title">{{$title}}</div>
        @endif
        
        <div class="notification-message">{{$message}}</div>
        
        @if($dismissible or !isset($dismissible))
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <span>&times;</span>
            </button>
        @endif
        
        <div class="notification-progress"></div>
    </div>
</div> 