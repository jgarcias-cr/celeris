<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Purpose: implement abstract persistence event behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when abstract persistence event functionality is required.
 */
abstract class AbstractPersistenceEvent implements PersistenceEventInterface
{
   private \DateTimeImmutable $occurredAt;

   /**
    * Create a new instance.
    *
    * @param object $entity
    * @param ?\DateTimeImmutable $occurredAt
    * @return mixed
    */
   public function __construct(private object $entity, ?\DateTimeImmutable $occurredAt = null)
   {
      $this->occurredAt = $occurredAt ?? new \DateTimeImmutable();
   }

   /**
    * Handle entity.
    *
    * @return object
    */
   public function entity(): object
   {
      return $this->entity;
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



