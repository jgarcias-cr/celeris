<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Purpose: implement unit of work behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when unit of work functionality is required.
 */
final class UnitOfWork
{
   /** @var array<int, object> */
   private array $new = [];
   /** @var array<int, object> */
   private array $dirty = [];
   /** @var array<int, object> */
   private array $removed = [];

   /**
    * Handle register new.
    *
    * @param object $entity
    * @return void
    */
   public function registerNew(object $entity): void
   {
      $id = spl_object_id($entity);
      $this->new[$id] = $entity;
      unset($this->removed[$id]);
   }

   /**
    * Handle register dirty.
    *
    * @param object $entity
    * @return void
    */
   public function registerDirty(object $entity): void
   {
      $id = spl_object_id($entity);
      if (isset($this->new[$id]) || isset($this->removed[$id])) {
         return;
      }

      $this->dirty[$id] = $entity;
   }

   /**
    * Handle register removed.
    *
    * @param object $entity
    * @return void
    */
   public function registerRemoved(object $entity): void
   {
      $id = spl_object_id($entity);
      unset($this->new[$id], $this->dirty[$id]);
      $this->removed[$id] = $entity;
   }

   /**
    * @return array<int, object>
    */
   public function newEntities(): array
   {
      return array_values($this->new);
   }

   /**
    * @return array<int, object>
    */
   public function dirtyEntities(): array
   {
      return array_values($this->dirty);
   }

   /**
    * @return array<int, object>
    */
   public function removedEntities(): array
   {
      return array_values($this->removed);
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->new = [];
      $this->dirty = [];
      $this->removed = [];
   }

   /**
    * Determine whether is empty.
    *
    * @return bool
    */
   public function isEmpty(): bool
   {
      return $this->new === [] && $this->dirty === [] && $this->removed === [];
   }
}



