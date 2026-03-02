<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Define the contract for bootable service provider interface behavior in the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface BootableServiceProviderInterface extends ServiceProviderInterface
{
   /**
    * Handle boot.
    *
    * @param ContainerInterface $container
    * @return void
    */
   public function boot(ContainerInterface $container): void;
}




