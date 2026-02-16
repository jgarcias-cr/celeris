# celeris/queue-manager

Queue manager package for Celeris with two core capabilities:
- Execute due jobs using a worker-style manager.
- Schedule tasks to run in the future and monitor queue health.

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

## Configuration

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

## Production recommendation

Run queue processing in a dedicated worker process (CLI/daemon/supervisor), not inside HTTP request handlers.

- HTTP/API requests should only enqueue or schedule tasks.
- Worker process should execute `runLoop()` or periodic `runOnce()`.
- This keeps queue execution, retries, and idle sleeps out of the request lifecycle and avoids user-facing latency spikes.

## Correct vs incorrect usage

Correct in HTTP request handlers (enqueue/schedule only):

```php
use Celeris\QueueManager\QueueManager;

final class ReportController
{
    public function generate(QueueManager $queue): array
    {
        $jobId = $queue->schedule(
            'reports.generate',
            ['report' => 'monthly', 'requested_by' => 'u-42'],
            delayMs: 0,
        );

        return ['accepted' => true, 'job_id' => $jobId];
    }
}
```

Correct in worker entrypoint (dedicated process):

```php
<?php

declare(strict_types=1);

// bootstrap app + container
$queue = $container->get(\Celeris\QueueManager\QueueManager::class);

while (true) {
    $queue->runOnce();
    usleep(250_000); // 250ms polling interval
}
```

Incorrect in HTTP request handlers (blocks request lifecycle):

```php
use Celeris\QueueManager\QueueManager;

final class BadController
{
    public function syncNow(QueueManager $queue): array
    {
        // Do not run looped processing inside web requests.
        $queue->runLoop();
        return ['ok' => true];
    }
}
```

`monitor()` returns:
- due, scheduled, pending, processing, retry
- succeeded, failed, dead_letter
- oldest due lag and next run timestamp
