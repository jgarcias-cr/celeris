# celeris/queue-manager

Queue manager package for Celeris with scheduling, retries, and queue health snapshots.

## Purpose

This package provides an application-level queue runner for background tasks.
It can enqueue immediate jobs, schedule future jobs, run worker passes, and expose queue health metrics.

Current default repository is in-memory.

## Install

```bash
composer require celeris/queue-manager
```

## Register provider

```php
if (class_exists(\Celeris\QueueManager\QueueManagerServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\QueueManager\QueueManagerServiceProvider());
}
```

## Configure In App Config

`celeris/queue-manager` reads `queue.manager.*` from `ConfigRepository`:

```php
return [
    'queue' => [
        'manager' => [
            'enabled' => true,
            'worker_id' => 'main-queue-worker',
            'claim_batch_size' => 100,
            'claim_lock_seconds' => 30,
            'default_max_attempts' => 5,
            'retry_backoff_ms' => 500,
            'idle_sleep_ms' => 250,
        ],
    ],
];
```

## `.env` Integration

This package does not require fixed env variable names by itself.
If your app prefers env-driven config, map your own keys in your `config/queue.php`.

Example app-level env keys:

```dotenv
QUEUE_MANAGER_ENABLED=true
QUEUE_MANAGER_ID=main-queue-worker
QUEUE_MANAGER_CLAIM_BATCH_SIZE=100
QUEUE_MANAGER_CLAIM_LOCK_SECONDS=30
QUEUE_MANAGER_DEFAULT_MAX_ATTEMPTS=5
QUEUE_MANAGER_RETRY_BACKOFF_MS=500
QUEUE_MANAGER_IDLE_SLEEP_MS=250
```

## Register task handlers

```php
use Celeris\QueueManager\Handler\InMemoryJobHandlerResolver;

$resolver = $container->get(InMemoryJobHandlerResolver::class);
$resolver->register('email.send', function (array $payload): void {
    // Send email based on payload
});
```

## Schedule jobs

```php
use Celeris\QueueManager\QueueManager;

$queue = $container->get(QueueManager::class);

// Run immediately
$queue->enqueue('email.send', ['to' => 'ana@example.com', 'subject' => 'Welcome']);

// Run in 10 minutes
$queue->schedule('report.generate', ['report' => 'daily'], delayMs: 10 * 60 * 1000);

// Run at a specific unix timestamp
$queue->schedule('cache.warm', ['scope' => 'home'], runAtUnix: time() + 3600);
```

## Run worker and monitor

```php
$report = $queue->runOnce();
$stats = $queue->monitor()->toArray();
```

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework` only (no additional native Celeris package dependency).
- You need async task execution with retry/dead-letter behavior.
- You need delayed scheduling and periodic worker execution.
- You want queue health counters for operations visibility.

You may skip it when:

- All work is synchronous and short-lived.
- Your app already uses another queue system.
