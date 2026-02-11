<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: implement provider registry behavior for the Container subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by container components when provider registry functionality is required.
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




