<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: define the contract for container interface behavior in the Container subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete container services and resolved via dependency injection.
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




