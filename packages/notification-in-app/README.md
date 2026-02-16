# celeris/notification-in-app

Official in-app notification adapter package for Celeris.

## Install

```bash
composer require celeris/notification-in-app
```

## Register provider

```php
if (class_exists(\Celeris\Notification\InApp\InAppNotificationServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\InApp\InAppNotificationServiceProvider());
}
```

## Config

Uses host app `config/notifications.php` under `channels.in_app`.

```php
'in_app' => [
    'enabled' => false,
    'connection' => null,
    'table' => 'app_notifications',
    'auto_create_table' => false,
],
```

## Payload shape

`InAppNotificationChannel` expects envelope payload as array:

```php
[
    'user_id' => '42',
    'title' => 'Transfer completed',
    'body' => 'Your transfer #A-1001 was approved.',
    'data' => ['transaction_id' => 'A-1001'],
]
```

`title` or `body` must be provided.
