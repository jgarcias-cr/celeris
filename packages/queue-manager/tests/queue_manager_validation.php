<?php

declare(strict_types=1);

require __DIR__ . '/../../framework/src/bootstrap.php';
require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\QueueManager\Contracts\QueueRepositoryInterface;
use Celeris\QueueManager\Handler\InMemoryJobHandlerResolver;
use Celeris\QueueManager\QueueJob;
use Celeris\QueueManager\QueueJobStatus;
use Celeris\QueueManager\QueueManager;
use Celeris\QueueManager\QueueManagerServiceProvider;

function assertTrue(string $label, bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function createQueueManager(): array
{
    $config = new ConfigRepository([
        'queue' => [
            'manager' => [
                'enabled' => true,
                'worker_id' => 'test-worker',
                'claim_batch_size' => 50,
                'claim_lock_seconds' => 5,
                'default_max_attempts' => 2,
                'retry_backoff_ms' => 0,
                'idle_sleep_ms' => 0,
            ],
        ],
    ]);

    $services = new ServiceRegistry();
    $services->singleton(ConfigRepository::class, static fn (ContainerInterface $c): ConfigRepository => $config);

    $provider = new QueueManagerServiceProvider();
    $provider->register($services);

    $container = new Container($services->all());
    $container->validateCircularDependencies();

    $manager = $container->get(QueueManager::class);
    $resolver = $container->get(InMemoryJobHandlerResolver::class);
    $repository = $container->get(QueueRepositoryInterface::class);

    assertTrue('queue manager must resolve', $manager instanceof QueueManager);
    assertTrue('resolver must resolve', $resolver instanceof InMemoryJobHandlerResolver);
    assertTrue('repository must resolve', $repository instanceof QueueRepositoryInterface);

    return [$manager, $resolver, $repository];
}

function testFutureScheduleNotClaimedUntilDue(): void
{
    [$manager, $resolver, $repository] = createQueueManager();

    $executed = 0;
    $resolver->register('reports.monthly', function (array $payload, QueueJob $job) use (&$executed): void {
        $executed++;
    });

    $id = $manager->schedule('reports.monthly', ['month' => '2026-02'], delayMs: 60_000);

    $report = $manager->runOnce();
    assertTrue('future job must not be claimed immediately', $report->claimed() === 0);
    assertTrue('future handler must not execute immediately', $executed === 0);

    $snapshot = $manager->monitor();
    assertTrue('scheduled count should be at least one', $snapshot->scheduled() >= 1);

    $claimed = $repository->claimDueBatch(10, microtime(true) + 120, 'test-worker', 5);
    assertTrue('future job should be claimable when time advances', count($claimed) === 1);

    $job = $repository->find($id);
    assertTrue('claimed future job should be processing', $job instanceof QueueJob && $job->status() === QueueJobStatus::PROCESSING);
}

function testRetryThenSuccessFlow(): void
{
    [$manager, $resolver, $repository] = createQueueManager();

    $attempts = 0;
    $resolver->register('sync.contacts', function (array $payload, QueueJob $job) use (&$attempts): void {
        $attempts++;
        if ($attempts === 1) {
            throw new RuntimeException('temporary failure');
        }
    });

    $id = $manager->enqueue('sync.contacts', ['tenant' => 'acme']);

    $first = $manager->runOnce();
    assertTrue('first pass should claim one job', $first->claimed() === 1);
    assertTrue('first pass should retry one job', $first->retried() === 1);
    assertTrue('first pass should record one error', $first->errors() === 1);

    $second = $manager->runOnce();
    assertTrue('second pass should claim one retry job', $second->claimed() === 1);
    assertTrue('second pass should succeed job', $second->succeeded() === 1);

    $job = $repository->find($id);
    assertTrue('job should be marked succeeded', $job instanceof QueueJob && $job->status() === QueueJobStatus::SUCCEEDED);
    assertTrue('job should track single failed attempt before success', $job instanceof QueueJob && $job->attemptCount() === 1);
}

function testMissingHandlerDeadLetters(): void
{
    [$manager, $resolver, $repository] = createQueueManager();

    $id = $manager->enqueue('task.without.handler', ['a' => 1]);

    $report = $manager->runOnce();
    assertTrue('missing handler must dead-letter job', $report->deadLettered() === 1);
    assertTrue('missing handler counter should increment', $report->missingHandler() === 1);

    $job = $repository->find($id);
    assertTrue('job should be dead-letter', $job instanceof QueueJob && $job->status() === QueueJobStatus::DEAD_LETTER);
}

function testPerJobMaxAttempts(): void
{
    [$manager, $resolver, $repository] = createQueueManager();

    $resolver->register('always.fails', function (array $payload, QueueJob $job): void {
        throw new RuntimeException('still broken');
    });

    $id = $manager->enqueue('always.fails', ['mode' => 'strict'], maxAttempts: 1);

    $report = $manager->runOnce();
    assertTrue('single-attempt job should dead-letter immediately', $report->deadLettered() === 1);
    assertTrue('single-attempt job should count an error', $report->errors() === 1);

    $job = $repository->find($id);
    assertTrue('single-attempt job should be dead-letter', $job instanceof QueueJob && $job->status() === QueueJobStatus::DEAD_LETTER);
    assertTrue('single-attempt job should record one attempt', $job instanceof QueueJob && $job->attemptCount() === 1);
}

testFutureScheduleNotClaimedUntilDue();
testRetryThenSuccessFlow();
testMissingHandlerDeadLetters();
testPerJobMaxAttempts();

echo "queue_manager_validation: ok\n";
