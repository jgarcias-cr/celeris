<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Route persistence event dispatcher events/messages to registered handlers.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PersistenceEventDispatcher
{
   /** @var array<string, array<int, callable(PersistenceEventInterface): void>> */
   private array $listeners = [];

   /**
    * Handle listen.
    *
    * @param string $eventName
    * @param callable $listener
    * @return void
    */
   public function listen(string $eventName, callable $listener): void
   {
      $name = trim($eventName);
      if ($name === '') {
         throw new \InvalidArgumentException('Event name cannot be empty.');
      }

      $this->listeners[$name] ??= [];
      $this->listeners[$name][] = $listener;
   }

   /**
    * Handle dispatch.
    *
    * @param PersistenceEventInterface $event
    * @return void
    */
   public function dispatch(PersistenceEventInterface $event): void
   {
      foreach ($this->resolveListeners($event) as $listener) {
         $listener($event);
      }
   }

   /**
    * @return array<int, callable(PersistenceEventInterface): void>
    */
   private function resolveListeners(PersistenceEventInterface $event): array
   {
      $resolved = [];

      if (isset($this->listeners['*'])) {
         $resolved = [...$resolved, ...$this->listeners['*']];
      }

      $className = $event::class;
      if (isset($this->listeners[$className])) {
         $resolved = [...$resolved, ...$this->listeners[$className]];
      }

      $name = $event->name();
      if (isset($this->listeners[$name])) {
         $resolved = [...$resolved, ...$this->listeners[$name]];
      }

      return $resolved;
   }
}



