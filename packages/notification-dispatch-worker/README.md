# celeris/notification-dispatch-worker

Outbox dispatch worker package for Celeris realtime notification delivery.

## Purpose

This package runs the background dispatch step of the notification pipeline.
It claims outbox rows, maps them to realtime events, publishes them to the realtime gateway, and updates outbox status (`sent`, `retry`, `dead_letter`).

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

## Configure In `.env`

In your API or MVC app, worker options come from `config/notifications.php` under `notifications.dispatch_worker.*` and `notifications.outbox.*`:

```dotenv
NOTIFICATIONS_DISPATCH_WORKER_ENABLED=true
NOTIFICATIONS_DISPATCH_WORKER_ID=dispatch-worker
NOTIFICATIONS_DISPATCH_WORKER_IDLE_SLEEP_MS=250

NOTIFICATIONS_OUTBOX_ENABLED=true
NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS=5
NOTIFICATIONS_OUTBOX_BACKOFF_MS=500
NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE=100
NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS=30
```

This worker also depends on a realtime gateway client. In your API or MVC app, that is configured with:

```dotenv
NOTIFICATIONS_REALTIME_ENABLED=true
NOTIFICATIONS_REALTIME_ENDPOINT=
NOTIFICATIONS_REALTIME_TIMEOUT_SECONDS=5
NOTIFICATIONS_REALTIME_SERVICE_ID=
NOTIFICATIONS_REALTIME_SERVICE_SECRET=
```

Configuration notes:

- `NOTIFICATIONS_DISPATCH_WORKER_ENABLED=false` makes `runOnce()` skip work.
- `NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS` and `NOTIFICATIONS_OUTBOX_BACKOFF_MS` control retry and dead-letter transitions.
- `NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE` and `NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS` control claim throughput and lock duration.

## Services

- `OutboxDispatchWorker` for one-pass or looped dispatch execution.
- `DispatchWorkerOptions` loaded from `notifications.outbox.*` and `notifications.dispatch_worker.*`.

## Basic usage

```php
$worker = $container->get(\Celeris\Notification\DispatchWorker\OutboxDispatchWorker::class);
$report = $worker->runOnce();
```

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework`, `celeris/notification-outbox`, and `celeris/notification-realtime-gateway-websocket`.
- You already enqueue notifications to outbox and need asynchronous realtime delivery.
- You want controlled retry/dead-letter behavior for gateway failures.
- You run a dedicated worker process outside the HTTP request lifecycle.

You may skip it when:

- You do not use outbox-based notification delivery.
- You do not publish notification events to a realtime gateway.
