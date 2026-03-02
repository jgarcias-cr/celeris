<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

/**
 * Define the contract for domain event listener interface behavior in the Domain subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



