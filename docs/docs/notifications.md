---
id: notifications
title: Flash Notifications
sidebar_position: 10
---

# Flash Notifications

Session-based flash messages that survive a redirect and are displayed once on the next page. Built on top of `Core\Helpers\NotificationHelper`.

## Sending a notification

Use the `Messages\Messages` facade to flash a message before a redirect:

```php
use Messages\Messages;

// Generic messages
Messages::Success('Profile updated.');
Messages::Error('Something went wrong.');
Messages::Warning('Your session will expire soon.');
Messages::Info('New version available.');

// Typed record messages (substitutes {type} automatically)
Messages::RecordCreated('User');   // → "User created successfully."
Messages::RecordUpdated('Post');   // → "Post updated successfully."
Messages::RecordDeleted('Order');  // → "Order deleted successfully."
Messages::RecordNotFound('Product'); // → "Product not found."

// Validation errors (array of field → message pairs)
Messages::ValidationErrors(['email' => 'Invalid email address.']);

header('Location: /users');
exit;
```

## Displaying notifications

Include the notification partial anywhere in your layout (typically just after `<body>`):

```blade
@include('_Notifications.notifications')
```

The partial reads flashed messages from the session and calls `LazyMePHP.Notifications.show()` for each one. Notifications appear as dismissible banners and auto-dismiss after a few seconds.

### Types and their visual style

| Type | CSS class | Default colour |
|---|---|---|
| `success` | `.notification-success` | Green |
| `error` | `.notification-error` | Red |
| `warning` | `.notification-warning` | Amber |
| `info` | `.notification-info` | Blue |
| `debug` | `.notification-debug` | Grey |

## Priority

Pass a priority (1–5) to control display order within a page load. Higher priority messages appear first:

```php
use Core\Helpers\NotificationHelper;

NotificationHelper::success('Low priority note', priority: 1);
NotificationHelper::error('Critical error!', priority: 5);
```

## Using NotificationHelper directly

`Messages\Messages` is a thin facade. The underlying class has more options:

```php
use Core\Helpers\NotificationHelper;

NotificationHelper::success('Done.');
NotificationHelper::error('Failed.');
NotificationHelper::warning('Watch out.', category: 'auth');
NotificationHelper::info('FYI.', priority: 3);
NotificationHelper::validationError(['name' => 'Required.']);

// Read and clear
$msgs = NotificationHelper::getAndClear(); // clears session after reading
$msgs = NotificationHelper::get();         // read only, leave in session

NotificationHelper::hasNotifications();     // bool
NotificationHelper::clear();               // clear all
NotificationHelper::clearByType('error');  // clear by type
NotificationHelper::clearByCategory('auth');
```

## Custom enum messages

`App/Messages/Error.php` and `App/Messages/Success.php` contain PHP enums with pre-defined messages. The `{type}` placeholder is substituted at runtime:

```php
// App/Messages/Error.php
enum Error: string
{
    case DB_RECORD_NOT_FOUND = "{type} not found.";

    public function getMessage(array $params = []): string
    {
        return str_replace(
            array_map(fn($k) => "{{$k}}", array_keys($params)),
            array_values($params),
            $this->value
        );
    }
}
```

Add your own cases to extend the vocabulary.
