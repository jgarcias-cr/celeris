<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

/**
 * Purpose: define the contract for domain event listener interface behavior in the Domain subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete domain services and resolved via dependency injection.
 */
interface DomainEventListenerInterface
{
   /**
    * Handle handle.
    *
    * @param DomainEventInterface $event
    * @return void
    */
   public function handle(DomainEventInterface $event): void;
}



