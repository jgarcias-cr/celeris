<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Model;

use Celeris\Framework\Database\ORM\Event\PersistenceEventInterface;

/**
 * Purpose: implement model lifecycle event behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when model lifecycle event functionality is required.
 */
final class ModelLifecycleEvent
{
   /**
    * Create a new instance.
    *
    * @param string $eventName
    * @param string $entityClass
    * @param float $occurredAt
    * @return mixed
    */
   public function __construct(
      private string $eventName,
      private string $entityClass,
      private float $occurredAt,
   ) {
   }

   /**
    * Create an instance from persistence event.
    *
    * @param PersistenceEventInterface $event
    * @return self
    */
   public static function fromPersistenceEvent(PersistenceEventInterface $event): self
   {
      return new self(
         $event->name(),
         $event->entity()::class,
         (float) $event->occurredAt()->format('U.u')
      );
   }

   /**
    * Handle event name.
    *
    * @return string
    */
   public function eventName(): string
   {
      return $this->eventName;
   }

   /**
    * Handle entity class.
    *
    * @return string
    */
   public function entityClass(): string
   {
      return $this->entityClass;
   }

   /**
    * Handle occurred at.
    *
    * @return float
    */
   public function occurredAt(): float
   {
      return $this->occurredAt;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'event' => $this->eventName,
         'entity_class' => $this->entityClass,
         'occurred_at' => $this->occurredAt,
      ];
   }
}



