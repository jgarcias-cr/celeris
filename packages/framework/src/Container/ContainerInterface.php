<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Define the contract for container interface behavior in the Container subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface ContainerInterface
{
   /**
    * Determine whether has.
    *
    * @param string $id
    * @return bool
    */
   public function has(string $id): bool;

   /**
    * Get the value.
    *
    * @param string $id
    * @return mixed
    */
   public function get(string $id): mixed;
}




