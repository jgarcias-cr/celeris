# celeris/notification-outbox

Transactional outbox package for Celeris notifications.

## Purpose

This package stores notification events in an outbox table inside the same database transaction as your domain write.
That gives you durable, retryable delivery instead of losing notifications when an external channel fails.

It provides:

- `OutboxServiceProvider` to register repository and writer services.
- `OutboxWriter` to enqueue messages transactionally.
- `OutboxRepositoryInterface` implementations for DB-backed and in-memory storage.

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

## Configure In `.env`

In your API or MVC app, outbox config is read from `config/notifications.php` under `notifications.outbox.*` using these environment variables:

```dotenv
NOTIFICATIONS_OUTBOX_ENABLED=true
NOTIFICATIONS_OUTBOX_CONNECTION=
NOTIFICATIONS_OUTBOX_TABLE=notification_outbox
NOTIFICATIONS_OUTBOX_AUTO_CREATE_TABLE=false
NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS=5
NOTIFICATIONS_OUTBOX_BACKOFF_MS=500
NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE=100
NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS=30
```

Configuration notes:

- `NOTIFICATIONS_OUTBOX_ENABLED=false` uses an in-memory repository (non-durable, mainly for local/dev scenarios).
- `NOTIFICATIONS_OUTBOX_CONNECTION` falls back to `database.default` when empty.
- `NOTIFICATIONS_OUTBOX_AUTO_CREATE_TABLE=true` auto-creates schema for SQLite, PostgreSQL, MySQL/MariaDB, and SQL Server.
- `NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS` and `NOTIFICATIONS_OUTBOX_BACKOFF_MS` control retry/dead-letter behavior.
- `NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE` and `NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS` tune worker claim throughput and lock duration.

## Basic Usage

```php
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxWriter;

$writer = $container->get(OutboxWriter::class);

$writer->transactional(
    function ($conn): void {
        // Persist your domain changes here.
    },
    [
        OutboxMessage::create(
            eventName: 'notification.created',
            aggregateType: 'notification',
            aggregateId: 'n-100',
            payload: ['user_id' => '42', 'channel' => 'in_app'],
        ),
    ],
);
```

To dispatch outbox rows to realtime/email channels, pair this package with `celeris/notification-dispatch-worker`.

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework` only (no additional native Celeris package dependency).
- You need notification enqueueing to succeed or fail atomically with database writes.
- You need retries and dead-letter handling for transient delivery failures.
- You want asynchronous processing to keep request latency predictable.

You may skip it when:

- Notifications are non-critical and best-effort synchronous delivery is acceptable.
