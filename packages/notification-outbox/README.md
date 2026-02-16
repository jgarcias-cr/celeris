# celeris/notification-outbox

Transactional outbox package for Celeris notifications.

## Install

```bash
composer require celeris/notification-outbox
```

## Register provider

```php
if (class_exists(\Celeris\Notification\Outbox\OutboxServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\Outbox\OutboxServiceProvider());
}
```

## Config

Uses host app `config/notifications.php` under `outbox`.

```php
'outbox' => [
    'enabled' => false,
    'connection' => null,
    'table' => 'notification_outbox',
    'auto_create_table' => false,
    'max_attempts' => 5,
    'backoff_ms' => 500,
    'claim_batch_size' => 100,
    'claim_lock_seconds' => 30,
],
```
