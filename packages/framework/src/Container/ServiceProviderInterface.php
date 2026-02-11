<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: define the contract for service provider interface behavior in the Container subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete container services and resolved via dependency injection.
 */
interface ServiceProviderInterface
{
   /**
    * Handle register.
    *
    * @param ServiceRegistry $services
    * @return void
    */
   public function register(ServiceRegistry $services): void;
}




