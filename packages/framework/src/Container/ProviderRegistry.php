<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Implement provider registry behavior for the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ProviderRegistry
{
   /** @var array<int, ServiceProviderInterface> */
   private array $providers = [];

   /**
    * Handle add.
    *
    * @param ServiceProviderInterface $provider
    * @return void
    */
   public function add(ServiceProviderInterface $provider): void
   {
      $this->providers[] = $provider;
   }

   /**
    * Handle register providers.
    *
    * @param ServiceRegistry $services
    * @return void
    */
   public function registerProviders(ServiceRegistry $services): void
   {
      foreach ($this->providers as $provider) {
         $provider->register($services);
      }
   }

   /**
    * Handle boot providers.
    *
    * @param ContainerInterface $container
    * @return void
    */
   public function bootProviders(ContainerInterface $container): void
   {
      foreach ($this->providers as $provider) {
         if ($provider instanceof BootableServiceProviderInterface) {
            $provider->boot($container);
         }
      }
   }
}




