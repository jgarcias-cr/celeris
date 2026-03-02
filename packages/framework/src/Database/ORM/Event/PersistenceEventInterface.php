<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Define the contract for persistence event interface behavior in the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



