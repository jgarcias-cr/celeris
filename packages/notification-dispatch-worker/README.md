# celeris/notification-dispatch-worker

Outbox dispatch worker package for Celeris realtime notification delivery.

## Install

```bash
composer require celeris/notification-dispatch-worker
```

## Register provider

```php
if (class_exists(\Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider());
}
```

## Services

- `OutboxDispatchWorker` for one-pass or looped dispatch execution.
- `DispatchWorkerOptions` loaded from `notifications.outbox.*` and `notifications.dispatch_worker.*`.

## Basic usage

```php
$worker = $container->get(\Celeris\Notification\DispatchWorker\OutboxDispatchWorker::class);
$report = $worker->runOnce();
```
