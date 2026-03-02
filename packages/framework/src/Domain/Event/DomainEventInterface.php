<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

/**
 * Define the contract for domain event interface behavior in the Domain subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface DomainEventInterface
{
   /**
    * Handle event id.
    *
    * @return string
    */
   public function eventId(): string;

   /**
    * Handle event name.
    *
    * @return string
    */
   public function eventName(): string;

   /**
    * Handle occurred at.
    *
    * @return \DateTimeImmutable
    */
   public function occurredAt(): \DateTimeImmutable;

   /**
    * @return array<string, mixed>
    */
   public function payload(): array;
}



