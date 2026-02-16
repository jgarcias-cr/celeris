<?php

declare(strict_types=1);

require __DIR__ . '/../../framework/src/bootstrap.php';
require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\Container;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Connection\InMemoryQueryTracer;
use Celeris\Framework\Database\Connection\PdoConnection;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxServiceProvider;
use Celeris\Notification\Outbox\OutboxStatus;
use Celeris\Notification\Outbox\OutboxWriter;
use Celeris\Notification\Outbox\Storage\InMemoryOutboxRepository;

function assertTrue(string $label, bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function testInMemoryRepositoryLifecycle(): void
{
    $repository = new InMemoryOutboxRepository();

    $message = OutboxMessage::create(
        eventName: 'notification.created',
        aggregateType: 'notification',
        aggregateId: 'n-100',
        payload: ['user_id' => '42', 'type' => 'in_app'],
    );

    $id = $repository->enqueue($message);
    assertTrue('enqueue should return id', $id !== '');

    $claimed = $repository->claimBatch(10, microtime(true), 'worker-1', 30);
    assertTrue('claimBatch should claim one message', count($claimed) === 1);
    assertTrue('claimed status should be processing', $claimed[0]->status() === OutboxStatus::PROCESSING);

    $failed = $repository->markFailed($id, 'temporary timeout', 3, 10, microtime(true));
    assertTrue('markFailed should succeed', $failed);

    $afterFail = $repository->find($id);
    assertTrue('message should exist after markFailed', $afterFail instanceof OutboxMessage);
    assertTrue('message status should be retry after first failure', $afterFail instanceof OutboxMessage && $afterFail->status() === OutboxStatus::RETRY);

    $repository->markFailed($id, 'retry timeout', 2, 10, microtime(true));
    $afterDeadLetter = $repository->find($id);
    assertTrue('message should become dead_letter after max attempts', $afterDeadLetter instanceof OutboxMessage && $afterDeadLetter->status() === OutboxStatus::DEAD_LETTER);

    $dead = $repository->deadLetters();
    assertTrue('deadLetters should return the dead letter message', count($dead) === 1);
}

function testProviderAndWriterWithSqlite(): void
{
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return;
    }

    $pdo = new PDO('sqlite::memory:');
    $connection = new PdoConnection('default', $pdo, new InMemoryQueryTracer(), DatabaseDriver::SQLite);

    $pool = new ConnectionPool();
    $pool->addConnection('default', $connection);

    $dbal = new DBAL($pool);
    $config = new ConfigRepository([
        'database' => [
            'default' => 'default',
            'connections' => [
                'default' => ['driver' => 'sqlite'],
            ],
        ],
        'notifications' => [
            'outbox' => [
                'enabled' => true,
                'connection' => 'default',
                'table' => 'test_notification_outbox',
                'auto_create_table' => true,
            ],
        ],
    ]);

    $services = new ServiceRegistry();
    $services->singleton(ConfigRepository::class, static fn (ContainerInterface $c): ConfigRepository => $config);
    $services->singleton(DBAL::class, static fn (ContainerInterface $c): DBAL => $dbal);

    $provider = new OutboxServiceProvider();
    $provider->register($services);

    $container = new Container($services->all());
    $container->validateCircularDependencies();

    $repository = $container->get(OutboxRepositoryInterface::class);
    assertTrue('provider should register OutboxRepositoryInterface', $repository instanceof OutboxRepositoryInterface);

    $writer = $container->get(OutboxWriter::class);
    assertTrue('provider should register OutboxWriter', $writer instanceof OutboxWriter);

    $id = $writer->transactional(
        static function (): string {
            return 'ok';
        },
        [
            OutboxMessage::create(
                eventName: 'notification.created',
                aggregateType: 'notification',
                aggregateId: 'n-200',
                payload: ['user_id' => '77'],
            ),
        ],
    );

    assertTrue('transactional callback should return original callback result', $id === 'ok');

    $claimed = $repository->claimBatch(5, microtime(true), 'worker-2', 30);
    assertTrue('repository should claim one stored message', count($claimed) === 1);

    $sent = $repository->markSent($claimed[0]->id());
    assertTrue('markSent should succeed', $sent);
}

testInMemoryRepositoryLifecycle();
testProviderAndWriterWithSqlite();

echo "outbox_validation: ok\n";
