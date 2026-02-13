<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\DatabaseDriver;
use InvalidArgumentException;
use Celeris\Sample\Pulse\Config\PulseSettings;
use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;
use Celeris\Sample\Pulse\Contracts\PulseRecorderInterface;
use Celeris\Sample\Pulse\Http\Controllers\PulseController;
use Celeris\Sample\Pulse\Http\Middleware\PulseAccessMiddleware;
use Celeris\Sample\Pulse\Monitoring\DatabaseMetricStore;
use Celeris\Sample\Pulse\Http\Middleware\RequestMetricsMiddleware;
use Celeris\Sample\Pulse\Monitoring\InMemoryMetricStore;
use Celeris\Sample\Pulse\Monitoring\PulseTableManager;
use Celeris\Sample\Pulse\Monitoring\PulseRecorder;

/**
 * Registers Pulse monitoring services into the host application container.
 *
 * This provider wires settings, storage backend selection (memory or
 * database), middleware, and HTTP controller bindings required by the
 * sample Pulse feature.
 */
final class PulseServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            PulseSettings::class,
            static fn (ContainerInterface $c): PulseSettings => PulseSettings::fromConfig($c->get(ConfigRepository::class)),
            [ConfigRepository::class],
        );

        $services->singleton(
            MetricStoreInterface::class,
            static fn (ContainerInterface $c): MetricStoreInterface => self::buildMetricStore($c),
            [PulseSettings::class, ConfigRepository::class, DBAL::class],
        );

        $services->singleton(
            PulseRecorderInterface::class,
            static fn (ContainerInterface $c): PulseRecorderInterface => new PulseRecorder($c->get(MetricStoreInterface::class)),
            [MetricStoreInterface::class],
        );

        $services->singleton(
            PulseRecorder::class,
            static fn (ContainerInterface $c): PulseRecorder => $c->get(PulseRecorderInterface::class),
            [PulseRecorderInterface::class],
        );

        $services->singleton(
            RequestMetricsMiddleware::class,
            static fn (ContainerInterface $c): RequestMetricsMiddleware => new RequestMetricsMiddleware(
                $c->get(MetricStoreInterface::class),
                $c->get(PulseSettings::class)->ignorePathPrefixes,
            ),
            [MetricStoreInterface::class, PulseSettings::class],
        );

        $services->singleton(
            PulseAccessMiddleware::class,
            static fn (ContainerInterface $c): PulseAccessMiddleware => new PulseAccessMiddleware(
                $c->get(ConfigRepository::class),
                $c->get(PulseSettings::class),
            ),
            [ConfigRepository::class, PulseSettings::class],
        );

        $services->singleton(
            PulseController::class,
            static fn (ContainerInterface $c): PulseController => new PulseController(
                $c->get(MetricStoreInterface::class),
                $c->get(PulseSettings::class),
            ),
            [MetricStoreInterface::class, PulseSettings::class],
        );
    }

    private static function buildMetricStore(ContainerInterface $container): MetricStoreInterface
    {
        $settings = $container->get(PulseSettings::class);

        if ($settings->storage === 'memory') {
            return self::buildInMemoryStore($settings);
        }

        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            throw new InvalidArgumentException('ConfigRepository is required to initialize Pulse database store.');
        }

        $dbal = $container->get(DBAL::class);
        if (!$dbal instanceof DBAL) {
            throw new InvalidArgumentException('DBAL is required to initialize Pulse database store.');
        }

        $connectionName = self::resolveConnectionName($settings, $config);
        $driver = self::resolveDriver($config, $connectionName);
        $tableName = PulseTableManager::normalizeTableName($settings->databaseTable);

        $store = new DatabaseMetricStore(
            connection: $dbal->connection($connectionName),
            driver: $driver,
            tableName: $tableName,
            requestSampleWindow: $settings->maxRequestSamples,
            taskSampleWindow: $settings->maxTaskSamples,
            appEnvironment: self::resolveAppEnvironment($config),
        );

        if ($settings->autoCreateTable) {
            $store->ensureTableIfMissing();
        }

        return $store;
    }

    private static function buildInMemoryStore(PulseSettings $settings): InMemoryMetricStore
    {
        return new InMemoryMetricStore(
            maxRequestSamples: $settings->maxRequestSamples,
            maxTaskSamples: $settings->maxTaskSamples,
        );
    }

    private static function resolveConnectionName(PulseSettings $settings, ConfigRepository $config): string
    {
        if ($settings->databaseConnection !== null && trim($settings->databaseConnection) !== '') {
            return trim($settings->databaseConnection);
        }

        $default = $config->get('database.default', 'default');
        return is_string($default) && trim($default) !== '' ? trim($default) : 'default';
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
                'Database connection "%s" is not configured for Pulse metrics storage.',
                $connectionName
            ));
        }

        $driver = $connection['driver'] ?? null;
        if (!is_scalar($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Database connection "%s" must define a driver for Pulse metrics storage.',
                $connectionName
            ));
        }

        return DatabaseDriver::fromString((string) $driver);
    }

    private static function resolveAppEnvironment(ConfigRepository $config): ?string
    {
        $env = $config->get('app.env', null);
        if (!is_scalar($env)) {
            return null;
        }

        $clean = trim((string) $env);
        return $clean === '' ? null : $clean;
    }
}
