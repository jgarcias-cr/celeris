<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

/**
 * Purpose: implement abstract domain event behavior for the Domain subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by domain components when abstract domain event functionality is required.
 */
abstract class AbstractDomainEvent implements DomainEventInterface
{
   private string $eventId;
   private \DateTimeImmutable $occurredAt;

   /**
    * Create a new instance.
    *
    * @param ?string $eventId
    * @param ?\DateTimeImmutable $occurredAt
    * @return mixed
    */
   public function __construct(?string $eventId = null, ?\DateTimeImmutable $occurredAt = null)
   {
      $this->eventId = $eventId !== null && trim($eventId) !== '' ? $eventId : bin2hex(random_bytes(16));
      $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
   }

   /**
    * Handle event id.
    *
    * @return string
    */
   public function eventId(): string
   {
      return $this->eventId;
   }

   /**
    * Handle event name.
    *
    * @return string
    */
   public function eventName(): string
   {
      return static::class;
   }

   /**
    * Handle occurred at.
    *
    * @return \DateTimeImmutable
    */
   public function occurredAt(): \DateTimeImmutable
   {
      return $this->occurredAt;
   }
}



