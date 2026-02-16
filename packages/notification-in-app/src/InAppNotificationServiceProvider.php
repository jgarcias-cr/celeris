<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\BootableServiceProviderInterface;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Notification\NotificationManager;
use Celeris\Notification\InApp\Contracts\NotificationStoreInterface;
use Celeris\Notification\InApp\Storage\DbalNotificationStore;
use InvalidArgumentException;

/**
 * Registers in-app notification channel services into the host application.
 */
final class InAppNotificationServiceProvider implements BootableServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            NotificationStoreInterface::class,
            static fn (ContainerInterface $container): NotificationStoreInterface => self::buildStore($container),
            [ConfigRepository::class, DBAL::class],
        );

        $services->singleton(
            InAppNotificationChannel::class,
            static fn (ContainerInterface $container): InAppNotificationChannel => new InAppNotificationChannel(
                $container->get(NotificationStoreInterface::class),
                'in_app',
            ),
            [NotificationStoreInterface::class],
        );
    }

    public function boot(ContainerInterface $container): void
    {
        if (!$container->has(ConfigRepository::class) || !$container->has(NotificationManager::class)) {
            return;
        }

        $config = $container->get(ConfigRepository::class);
        $manager = $container->get(NotificationManager::class);
        if (!$config instanceof ConfigRepository || !$manager instanceof NotificationManager) {
            return;
        }

        if (!self::toBool($config->get('notifications.channels.in_app.enabled', false))) {
            return;
        }

        $channel = $container->get(InAppNotificationChannel::class);
        if ($channel instanceof InAppNotificationChannel) {
            $manager->registerChannel($channel, 'in_app');
        }
    }

    private static function buildStore(ContainerInterface $container): NotificationStoreInterface
    {
        $config = $container->get(ConfigRepository::class);
        $dbal = $container->get(DBAL::class);
        if (!$config instanceof ConfigRepository || !$dbal instanceof DBAL) {
            throw new InvalidArgumentException('ConfigRepository and DBAL are required to build in-app notification store.');
        }

        $connectionName = self::resolveConnectionName($config);
        $tableName = (string) $config->get('notifications.channels.in_app.table', 'app_notifications');
        $autoCreateTable = self::toBool($config->get('notifications.channels.in_app.auto_create_table', false));

        $driver = self::resolveDriver($config, $connectionName);
        $store = new DbalNotificationStore(
            connection: $dbal->connection($connectionName),
            driver: $driver,
            tableName: $tableName,
        );

        if ($autoCreateTable) {
            $store->ensureTableIfMissing();
        }

        return $store;
    }

    private static function resolveConnectionName(ConfigRepository $config): string
    {
        $configured = trim((string) $config->get('notifications.channels.in_app.connection', ''));
        if ($configured !== '') {
            return $configured;
        }

        $databaseDefault = trim((string) $config->get('database.default', 'default'));
        return $databaseDefault !== '' ? $databaseDefault : 'default';
    }

    private static function resolveDriver(ConfigRepository $config, string $connectionName): DatabaseDriver
    {
        $connections = $config->get('database.connections', []);
        if (!is_array($connections)) {
            throw new InvalidArgumentException('database.connections must be an array.');
        }

        $connection = $connections[$connectionName] ?? null;
        if (!is_array($connection)) {
            throw new InvalidArgumentException(sprintf(
                'Database connection "%s" is not configured for in-app notifications.',
                $connectionName
            ));
        }

        $driver = $connection['driver'] ?? null;
        if (!is_scalar($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Database connection "%s" must define a driver for in-app notifications.',
                $connectionName
            ));
        }

        return DatabaseDriver::fromString((string) $driver);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed ?? false;
        }

        return false;
    }
}
