<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Define the contract for service provider interface behavior in the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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




