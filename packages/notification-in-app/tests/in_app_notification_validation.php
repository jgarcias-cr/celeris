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
use Celeris\Framework\Notification\NotificationEnvelope;
use Celeris\Framework\Notification\NotificationManager;
use Celeris\Notification\InApp\Contracts\NotificationStoreInterface;
use Celeris\Notification\InApp\InAppNotificationChannel;
use Celeris\Notification\InApp\InAppNotificationServiceProvider;
use Celeris\Notification\InApp\Storage\InMemoryNotificationStore;

function assertTrue(string $label, bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
}

function testChannelWithInMemoryStore(): void
{
    $store = new InMemoryNotificationStore();
    $channel = new InAppNotificationChannel($store);

    $ok = $channel->send(new NotificationEnvelope(
        type: 'in_app',
        payload: [
            'user_id' => 'user-42',
            'title' => 'Transfer approved',
            'body' => 'Your transfer #A-1001 was approved.',
            'data' => ['transaction_id' => 'A-1001'],
        ],
        channel: 'in_app',
    ));

    assertTrue('in_app channel should deliver valid payload', $ok->isDelivered());
    assertTrue('provider message id should be generated notification id', $ok->providerMessageId() !== null);

    $invalid = $channel->send(new NotificationEnvelope('in_app', ['title' => 'Missing user id'], 'in_app'));
    assertTrue('in_app channel should reject payload without user_id', !$invalid->isDelivered());
}

function testProviderBootAndStoreFlow(): void
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
            'default_channel' => 'in_app',
            'channels' => [
                'null' => ['enabled' => true],
                'in_app' => [
                    'enabled' => true,
                    'connection' => 'default',
                    'table' => 'test_app_notifications',
                    'auto_create_table' => true,
                ],
            ],
        ],
    ]);

    $manager = NotificationManager::fromConfig($config);

    $services = new ServiceRegistry();
    $services->singleton(ConfigRepository::class, static fn (ContainerInterface $c): ConfigRepository => $config);
    $services->singleton(DBAL::class, static fn (ContainerInterface $c): DBAL => $dbal);
    $services->singleton(NotificationManager::class, static fn (ContainerInterface $c): NotificationManager => $manager);

    $provider = new InAppNotificationServiceProvider();
    $provider->register($services);

    $container = new Container($services->all());
    $container->validateCircularDependencies();
    $provider->boot($container);

    assertTrue('provider should register in_app channel when enabled', $manager->hasChannel('in_app'));

    $result = $manager->send(new NotificationEnvelope(
        type: 'in_app',
        payload: [
            'user_id' => 'user-99',
            'title' => 'Job started',
            'data' => ['job' => 'sync'],
        ],
        channel: 'in_app',
    ));

    assertTrue('manager should send via in_app channel', $result->isDelivered());
    $id = (string) $result->providerMessageId();

    $store = $container->get(NotificationStoreInterface::class);
    assertTrue('notification store should be available in container', $store instanceof NotificationStoreInterface);

    $list = $store->listForUser('user-99', 20, true);
    assertTrue('store should return unread notification for user', count($list) === 1);

    $marked = $store->markRead($id);
    assertTrue('markRead should succeed for persisted id', $marked);

    $afterRead = $store->listForUser('user-99', 20, true);
    assertTrue('unread list should be empty after markRead', count($afterRead) === 0);
}

testChannelWithInMemoryStore();
testProviderBootAndStoreFlow();

echo "in_app_notification_validation: ok\n";
