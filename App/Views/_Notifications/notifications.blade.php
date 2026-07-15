<?php
/**
 * Notification bridge — drains the session queue and hands each notification
 * to LazyMePHP.Notifications.show() (defined in LazyMePHP.js).
 * No styles or JS logic live here; everything is in css.css / LazyMePHP.js.
 */
use Core\Helpers\NotificationHelper;
$notifications = NotificationHelper::getAndClear();
?>

<div id="notification-container" class="notification-container" role="status" aria-live="polite" aria-atomic="false"></div>

<?php if (!empty($notifications)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
<?php foreach ($notifications as $n): ?>
    LazyMePHP.Notifications.show(
        <?= json_encode($n['type']     ?? 'info',   JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        <?= json_encode($n['message']  ?? '',        JSON_HEX_TAG | JSON_HEX_AMP) ?>,
        {
            category: <?= json_encode($n['category'] ?? 'system', JSON_HEX_TAG | JSON_HEX_AMP) ?>,
            priority: <?= (int)($n['priority'] ?? 2) ?>
        }
    );
<?php endforeach; ?>
});
</script>
<?php endif; ?>
