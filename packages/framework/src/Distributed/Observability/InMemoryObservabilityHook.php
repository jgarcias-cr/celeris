<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Purpose: implement in memory observability hook behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when in memory observability hook functionality is required.
 */
final class InMemoryObservabilityHook implements ObservabilityHookInterface
{
   /** @var array<int, ObservabilityEvent> */
   private array $events = [];

   /**
    * Handle on event.
    *
    * @param ObservabilityEvent $event
    * @return void
    */
   public function onEvent(ObservabilityEvent $event): void
   {
      $this->events[] = $event;
   }

   /**
    * @return array<int, ObservabilityEvent>
    */
   public function events(): array
   {
      return $this->events;
   }

   /**
    * @return array<int, ObservabilityEvent>
    */
   public function eventsByName(string $name): array
   {
      return array_values(array_filter(
         $this->events,
         static fn (ObservabilityEvent $event): bool => $event->name() === $name
      ));
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->events = [];
   }
}



