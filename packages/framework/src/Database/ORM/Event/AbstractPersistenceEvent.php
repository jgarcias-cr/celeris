<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Implement abstract persistence event behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



