<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: define the contract for bootable service provider interface behavior in the Container subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete container services and resolved via dependency injection.
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




