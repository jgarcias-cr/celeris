<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Purpose: define the contract for persistence event interface behavior in the Database subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete database services and resolved via dependency injection.
 */
interface PersistenceEventInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string;

   /**
    * Handle entity.
    *
    * @return object
    */
   public function entity(): object;

   /**
    * Handle occurred at.
    *
    * @return \DateTimeImmutable
    */
   public function occurredAt(): \DateTimeImmutable;
}



