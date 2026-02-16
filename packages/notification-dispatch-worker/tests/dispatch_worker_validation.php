<?php

declare(strict_types=1);

require __DIR__ . '/../../framework/src/bootstrap.php';
require __DIR__ . '/../../notification-outbox/src/bootstrap.php';
require __DIR__ . '/../../notification-realtime-gateway-websocket/src/bootstrap.php';
require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\DBAL;
use Celeris\Notification\DispatchWorker\DispatchWorkerOptions;
use Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider;
use Celeris\Notification\DispatchWorker\OutboxDispatchWorker;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxServiceProvider;
use Celeris\Notification\Outbox\OutboxStatus;
use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;
use Celeris\Notification\RealtimeGateway\RealtimeEventMessage;
use Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider;
use Celeris\Notification\RealtimeGateway\RealtimePublishResult;

final class FakeRealtimeGatewayClient implements RealtimeGatewayClientInterface
{
    /** @var array<int, RealtimePublishResult> */
    private array $queue = [];

    /** @var array<int, RealtimeEventMessage> */
    public array $published = [];

    /** @param array<int, RealtimePublishResult> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function publish(RealtimeEventMessage $message): RealtimePublishResult
    {
        $this->published[] = $message;
        if ($this->queue === []) {
            return RealtimePublishResult::published(200, 'ok');
        }

        return array_shift($this->queue) ?? RealtimePublishResult::published(200, 'ok');
    }
}

function assertTrue(string $label, bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function createWorker(FakeRealtimeGatewayClient $gateway, ?OutboxRepositoryInterface &$repository = null): OutboxDispatchWorker
{
    $config = new ConfigRepository([
        'notifications' => [
            'outbox' => [
                'enabled' => false,
                'max_attempts' => 2,
                'backoff_ms' => 0,
                'claim_batch_size' => 50,
                'claim_lock_seconds' => 5,
            ],
            'dispatch_worker' => [
                'enabled' => true,
                'worker_id' => 'test-worker',
                'idle_sleep_ms' => 0,
            ],
            'realtime' => [
                'enabled' => true,
                'endpoint' => 'http://example.invalid/publish',
                'timeout_seconds' => 2,
                'service_id' => 'svc',
                'service_secret' => 'secret',
            ],
        ],
    ]);

    $services = new ServiceRegistry();
    $services->singleton(ConfigRepository::class, static fn (ContainerInterface $c): ConfigRepository => $config);
    $services->singleton(DBAL::class, static fn (ContainerInterface $c): DBAL => new DBAL(new ConnectionPool()));

    $outboxProvider = new OutboxServiceProvider();
    $outboxProvider->register($services);

    $realtimeProvider = new RealtimeGatewayServiceProvider();
    $realtimeProvider->register($services);

    // Override realtime client with fake for deterministic tests.
    $services->singleton(
        RealtimeGatewayClientInterface::class,
        static fn (ContainerInterface $c): RealtimeGatewayClientInterface => $gateway,
        [],
        true,
    );

    $dispatchProvider = new NotificationDispatchWorkerServiceProvider();
    $dispatchProvider->register($services);

    $container = new Container($services->all());
    $container->validateCircularDependencies();

    $repository = $container->get(OutboxRepositoryInterface::class);
    assertTrue('repository should be available', $repository instanceof OutboxRepositoryInterface);

    $options = $container->get(DispatchWorkerOptions::class);
    assertTrue('worker options should be available', $options instanceof DispatchWorkerOptions && $options->enabled);

    $worker = $container->get(OutboxDispatchWorker::class);
    assertTrue('worker should be available from container', $worker instanceof OutboxDispatchWorker);

    return $worker;
}

function enqueue(OutboxRepositoryInterface $repository, array $payload, string $event = 'notification.created'): string
{
    return $repository->enqueue(OutboxMessage::create(
        eventName: $event,
        aggregateType: 'notification',
        aggregateId: 'n-' . bin2hex(random_bytes(4)),
        payload: $payload,
    ));
}

function testPublishedFlow(): void
{
    $gateway = new FakeRealtimeGatewayClient([
        RealtimePublishResult::published(202, 'queued'),
    ]);

    $worker = createWorker($gateway, $repository);
    $id = enqueue($repository, ['user_id' => '42', 'notification_id' => 'n-1']);

    $report = $worker->runOnce();

    assertTrue('one item should be claimed', $report->claimed() === 1);
    assertTrue('one item should be published', $report->published() === 1);

    $message = $repository->find($id);
    assertTrue('message should be marked sent', $message instanceof OutboxMessage && $message->status() === OutboxStatus::SENT);
}

function testRetryAndDeadLetterByMaxAttempts(): void
{
    $gateway = new FakeRealtimeGatewayClient([
        RealtimePublishResult::retryableFailure('timeout', 503),
        RealtimePublishResult::retryableFailure('timeout again', 503),
    ]);

    $worker = createWorker($gateway, $repository);
    $id = enqueue($repository, ['user_id' => '42', 'notification_id' => 'n-2']);

    $first = $worker->runOnce();
    assertTrue('first run should schedule retry', $first->retryScheduled() === 1);

    $second = $worker->runOnce();
    assertTrue('second run should dead-letter by max attempts', $second->deadLettered() === 1);

    $message = $repository->find($id);
    assertTrue('message should be dead_letter after max attempts', $message instanceof OutboxMessage && $message->status() === OutboxStatus::DEAD_LETTER);
}

function testTerminalFailure(): void
{
    $gateway = new FakeRealtimeGatewayClient([
        RealtimePublishResult::terminalFailure('unauthorized', 401),
    ]);

    $worker = createWorker($gateway, $repository);
    $id = enqueue($repository, ['user_id' => '42', 'notification_id' => 'n-3']);

    $report = $worker->runOnce();
    assertTrue('terminal failure should be counted', $report->terminalFailed() === 1);
    assertTrue('terminal failure should dead-letter item', $report->deadLettered() === 1);

    $message = $repository->find($id);
    assertTrue('message should be dead_letter on terminal failure', $message instanceof OutboxMessage && $message->status() === OutboxStatus::DEAD_LETTER);
}

function testMappingFailure(): void
{
    $gateway = new FakeRealtimeGatewayClient([]);
    $worker = createWorker($gateway, $repository);
    $id = enqueue($repository, ['notification_id' => 'n-4']);

    $report = $worker->runOnce();
    assertTrue('mapping failure should increment skipped', $report->skipped() >= 1);
    assertTrue('mapping failure should dead-letter item', $report->deadLettered() === 1);

    $message = $repository->find($id);
    assertTrue('message should be dead_letter when mapping fails', $message instanceof OutboxMessage && $message->status() === OutboxStatus::DEAD_LETTER);
}

testPublishedFlow();
testRetryAndDeadLetterByMaxAttempts();
testTerminalFailure();
testMappingFailure();

echo "dispatch_worker_validation: ok\n";
