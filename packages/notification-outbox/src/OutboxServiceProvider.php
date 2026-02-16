<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\Storage\DbalOutboxRepository;
use Celeris\Notification\Outbox\Storage\InMemoryOutboxRepository;
use InvalidArgumentException;

/**
 * Registers outbox repository and transactional writer services.
 */
final class OutboxServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            OutboxRepositoryInterface::class,
            static fn (ContainerInterface $container): OutboxRepositoryInterface => self::buildRepository($container),
            [ConfigRepository::class, DBAL::class],
        );

        $services->singleton(
            OutboxWriter::class,
            static fn (ContainerInterface $container): OutboxWriter => self::buildWriter($container),
            [OutboxRepositoryInterface::class, ConfigRepository::class, DBAL::class],
        );
    }

    private static function buildRepository(ContainerInterface $container): OutboxRepositoryInterface
    {
        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            throw new InvalidArgumentException('ConfigRepository is required to initialize notification outbox repository.');
        }

        $enabled = self::toBool($config->get('notifications.outbox.enabled', false));
        if (!$enabled) {
            return new InMemoryOutboxRepository();
        }

        $dbal = $container->get(DBAL::class);
        if (!$dbal instanceof DBAL) {
            throw new InvalidArgumentException('DBAL is required to initialize notification outbox repository.');
        }

        $connectionName = self::resolveConnectionName($config);
        $tableName = trim((string) $config->get('notifications.outbox.table', 'notification_outbox'));
        $driver = self::resolveDriver($config, $connectionName);

        $repository = new DbalOutboxRepository(
            connection: $dbal->connection($connectionName),
            driver: $driver,
            tableName: $tableName,
        );

        if (self::toBool($config->get('notifications.outbox.auto_create_table', false))) {
            $repository->ensureStorage();
        }

        return $repository;
    }

    private static function buildWriter(ContainerInterface $container): OutboxWriter
    {
        $repository = $container->get(OutboxRepositoryInterface::class);
        $config = $container->get(ConfigRepository::class);
        $dbal = $container->get(DBAL::class);

        if (!$repository instanceof OutboxRepositoryInterface || !$config instanceof ConfigRepository || !$dbal instanceof DBAL) {
            throw new InvalidArgumentException('Unable to build OutboxWriter due to missing dependencies.');
        }

        $connectionName = self::resolveConnectionName($config);
        $connection = $dbal->connection($connectionName);
        if (!$connection instanceof ConnectionInterface) {
            throw new InvalidArgumentException('Outbox writer requires a database connection.');
        }

        return new OutboxWriter($connection, $repository);
    }

    private static function resolveConnectionName(ConfigRepository $config): string
    {
        $configured = trim((string) $config->get('notifications.outbox.connection', ''));
        if ($configured !== '') {
            return $configured;
        }

        $default = trim((string) $config->get('database.default', 'default'));
        return $default !== '' ? $default : 'default';
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
                'Database connection "%s" is not configured for outbox repository.',
                $connectionName
            ));
        }

        $driver = $connection['driver'] ?? null;
        if (!is_scalar($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Database connection "%s" must define a driver for outbox repository.',
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
