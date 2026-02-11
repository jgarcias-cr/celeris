<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: implement resolution state behavior for the Container subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by container components when resolution state functionality is required.
 */
final class ResolutionState
{
   /** @var array<int, string> */
   private array $serviceStack = [];
   /** @var array<int, ServiceLifetime> */
   private array $lifetimeStack = [];

   /**
    * Handle enter.
    *
    * @param string $id
    * @param ServiceLifetime $lifetime
    * @return void
    */
   public function enter(string $id, ServiceLifetime $lifetime): void
   {
      $this->serviceStack[] = $id;
      $this->lifetimeStack[] = $lifetime;
   }

   /**
    * Handle leave.
    *
    * @return void
    */
   public function leave(): void
   {
      array_pop($this->serviceStack);
      array_pop($this->lifetimeStack);
   }

   /**
    * Handle contains.
    *
    * @param string $id
    * @return bool
    */
   public function contains(string $id): bool
   {
      return in_array($id, $this->serviceStack, true);
   }

   /**
    * @return array<int, string>
    */
   public function cyclePath(string $id): array
   {
      $index = array_search($id, $this->serviceStack, true);
      if ($index === false) {
         return [$id, $id];
      }

      return [...array_slice($this->serviceStack, (int) $index), $id];
   }

   /**
    * Determine whether has lifetime.
    *
    * @param ServiceLifetime $lifetime
    * @return bool
    */
   public function hasLifetime(ServiceLifetime $lifetime): bool
   {
      foreach ($this->lifetimeStack as $item) {
         if ($item === $lifetime) {
            return true;
         }
      }

      return false;
   }
}




