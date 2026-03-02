<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

use Celeris\Framework\Container\BootableServiceProviderInterface;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\ActiveRecord\Resolver\DbalEntityManagerResolver;
use Celeris\Framework\Database\ActiveRecord\Resolver\EntityManagerResolverInterface;
use Celeris\Framework\Database\ActiveRecord\Validation\ActiveRecordValidatorInterface;
use Celeris\Framework\Database\ActiveRecord\Validation\MetadataConstraintValidator;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\MetadataFactory;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;

/**
 * Register and boot the optional Active Record compatibility services.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ActiveRecordServiceProvider implements BootableServiceProviderInterface
{
   /**
    * Register Active Record services in the container registry.
    *
    * @param ServiceRegistry $services
    * @return void
    */
   public function register(ServiceRegistry $services): void
   {
      $services->singleton(
         MetadataFactory::class,
         static fn (ContainerInterface $container): MetadataFactory => new MetadataFactory(),
         [],
         false,
      );

      $services->singleton(
         ActiveRecordValidatorInterface::class,
         static fn (ContainerInterface $container): ActiveRecordValidatorInterface => new MetadataConstraintValidator(),
         [],
         false,
      );

      $services->singleton(
         EntityManagerResolverInterface::class,
         static fn (ContainerInterface $container): EntityManagerResolverInterface => new DbalEntityManagerResolver(
            $container->get(DBAL::class),
            $container->get(MetadataFactory::class),
            null,
            $container->get(DomainEventDispatcher::class),
         ),
         [DBAL::class, MetadataFactory::class, DomainEventDispatcher::class],
         false,
      );

      $services->singleton(
         ActiveRecordManager::class,
         static fn (ContainerInterface $container): ActiveRecordManager => new ActiveRecordManager(
            $container->get(EntityManagerResolverInterface::class),
            $container->get(MetadataFactory::class),
            $container->get(ActiveRecordValidatorInterface::class),
         ),
         [EntityManagerResolverInterface::class, MetadataFactory::class, ActiveRecordValidatorInterface::class],
         false,
      );
   }

   /**
    * Bind the resolved Active Record manager into the static model facade.
    *
    * @param ContainerInterface $container
    * @return void
    */
   public function boot(ContainerInterface $container): void
   {
      if (!$container->has(ActiveRecordManager::class)) {
         return;
      }

      $manager = $container->get(ActiveRecordManager::class);
      if ($manager instanceof ActiveRecordManager) {
         ActiveRecordModel::setManager($manager);
      }
   }
}
