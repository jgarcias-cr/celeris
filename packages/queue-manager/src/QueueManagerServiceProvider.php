<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\QueueManager\Contracts\JobHandlerResolverInterface;
use Celeris\QueueManager\Contracts\QueueRepositoryInterface;
use Celeris\QueueManager\Handler\InMemoryJobHandlerResolver;
use Celeris\QueueManager\Storage\InMemoryQueueRepository;

final class QueueManagerServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            QueueManagerOptions::class,
            static fn (ContainerInterface $container): QueueManagerOptions => self::buildOptions($container),
            [ConfigRepository::class],
        );

        $services->singleton(
            InMemoryQueueRepository::class,
            static fn (ContainerInterface $container): InMemoryQueueRepository => new InMemoryQueueRepository(),
            [],
        );

        $services->singleton(
            QueueRepositoryInterface::class,
            static fn (ContainerInterface $container): QueueRepositoryInterface => $container->get(InMemoryQueueRepository::class),
            [InMemoryQueueRepository::class],
        );

        $services->singleton(
            InMemoryJobHandlerResolver::class,
            static fn (ContainerInterface $container): InMemoryJobHandlerResolver => new InMemoryJobHandlerResolver(),
            [],
        );

        $services->singleton(
            JobHandlerResolverInterface::class,
            static fn (ContainerInterface $container): JobHandlerResolverInterface => $container->get(InMemoryJobHandlerResolver::class),
            [InMemoryJobHandlerResolver::class],
        );

        $services->singleton(
            QueueManager::class,
            static fn (ContainerInterface $container): QueueManager => new QueueManager(
                repository: $container->get(QueueRepositoryInterface::class),
                handlers: $container->get(JobHandlerResolverInterface::class),
                options: $container->get(QueueManagerOptions::class),
            ),
            [QueueRepositoryInterface::class, JobHandlerResolverInterface::class, QueueManagerOptions::class],
        );
    }

    private static function buildOptions(ContainerInterface $container): QueueManagerOptions
    {
        $config = $container->get(ConfigRepository::class);
        if (!$config instanceof ConfigRepository) {
            return new QueueManagerOptions();
        }

        return QueueManagerOptions::fromConfig($config);
    }
}
