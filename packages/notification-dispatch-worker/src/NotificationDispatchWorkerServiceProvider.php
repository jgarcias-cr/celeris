<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Notification\DispatchWorker\Contracts\DispatchMetricsInterface;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;

final class NotificationDispatchWorkerServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            DispatchWorkerOptions::class,
            static fn (ContainerInterface $container): DispatchWorkerOptions => self::buildOptions($container),
            [ConfigRepository::class],
        );

        $services->singleton(
            DispatchMetricsInterface::class,
            static fn (ContainerInterface $container): DispatchMetricsInterface => new NullDispatchMetrics(),
            [],
        );

        $services->singleton(
            OutboxRealtimeMessageMapper::class,
            static fn (ContainerInterface $container): OutboxRealtimeMessageMapper => new OutboxRealtimeMessageMapper(),
            [],
        );

        $services->singleton(
            OutboxDispatchWorker::class,
            static fn (ContainerInterface $container): OutboxDispatchWorker => new OutboxDispatchWorker(
                repository: $container->get(OutboxRepositoryInterface::class),
                gateway: $container->get(RealtimeGatewayClientInterface::class),
                options: $container->get(DispatchWorkerOptions::class),
                mapper: $container->get(OutboxRealtimeMessageMapper::class),
                metrics: $container->get(DispatchMetricsInterface::class),
            ),
            [
                OutboxRepositoryInterface::class,
                RealtimeGatewayClientInterface::class,
                DispatchWorkerOptions::class,
                OutboxRealtimeMessageMapper::class,
                DispatchMetricsInterface::class,
            ],
        );
    }

    private static function buildOptions(ContainerInterface $container): DispatchWorkerOptions
    {
        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            return new DispatchWorkerOptions();
        }

        return DispatchWorkerOptions::fromConfig($config);
    }
}
