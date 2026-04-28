<?php

declare(strict_types=1);

namespace Celeris\Framework\Domain\Event;

use InvalidArgumentException;

/**
 * Route domain event dispatcher events/messages to registered handlers.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DomainEventDispatcher
{
   /** @var array<string, array<int, callable(DomainEventInterface): void>> */
   private array $listeners = [];
   /** @var array<int, DomainEventInterface> */
   private array $history = [];

   /**
    * Handle listen.
    *
    * @param string $eventName
    * @param callable|DomainEventListenerInterface $listener
    * @return void
    */
   public function listen(string $eventName, callable|DomainEventListenerInterface $listener): void
   {
      $name = trim($eventName);
      if ($name === '') {
         throw new InvalidArgumentException('Event name cannot be empty.');
      }

      $this->listeners[$name] ??= [];
      $this->listeners[$name][] = $this->normalizeListener($listener);
   }

   /**
    * Handle dispatch.
    *
    * @param DomainEventInterface $event
    * @return void
    */
   public function dispatch(DomainEventInterface $event): void
   {
      $this->history[] = $event;

      foreach ($this->listenersFor($event) as $listener) {
         $listener($event);
      }
   }

   /**
    * Handle dispatch many.
    * @param array<int, DomainEventInterface> $events
    */
   public function dispatchMany(array $events): void
   {
      foreach ($events as $event) {
         $this->dispatch($event);
      }
   }

   /**
    * Handle history.
    * @return array<int, DomainEventInterface>
    */
   public function history(): array
   {
      return $this->history;
   }

   /**
    * Handle clear history.
    *
    * @return void
    */
   public function clearHistory(): void
   {
      $this->history = [];
   }

   /**
    * Handle listeners for event.
    *
    * Listeners are ordered as follows:
    * 1. Global listeners registered with '*'.
    * 2. Listeners registered for the event's class name.
    * 3. Listeners registered for the event's name (if different from class name).
    *
    * This allows for flexible listener registration while ensuring a predictable execution order.
    * The method ensures that all relevant listeners are collected and returned in the correct order for dispatching.
    * @param DomainEventInterface $event
    * @return array<int, callable(DomainEventInterface): void>
    */
   private function listenersFor(DomainEventInterface $event): array
   {
      $ordered = [];

      if (isset($this->listeners['*'])) {
         $ordered = [...$ordered, ...$this->listeners['*']];
      }

      $className = $event::class;
      if (isset($this->listeners[$className])) {
         $ordered = [...$ordered, ...$this->listeners[$className]];
      }

      $eventName = $event->eventName();
      if ($eventName !== $className && isset($this->listeners[$eventName])) {
         $ordered = [...$ordered, ...$this->listeners[$eventName]];
      }

      return $ordered;
   }

   /**
    * Normalize listener to a callable that accepts a DomainEventInterface.
    * @return callable(DomainEventInterface): void
    */
   private function normalizeListener(callable|DomainEventListenerInterface $listener): callable
   {
      if ($listener instanceof DomainEventListenerInterface) {
         return static function (DomainEventInterface $event) use ($listener): void {
            $listener->handle($event);
         };
      }

      return static function (DomainEventInterface $event) use ($listener): void {
         $listener($event);
      };
   }
}



