<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

/**
 * Purpose: define the contract for domain event interface behavior in the Domain subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete domain services and resolved via dependency injection.
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



