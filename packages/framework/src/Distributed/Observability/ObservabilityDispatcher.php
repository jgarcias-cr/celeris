<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Purpose: route observability dispatcher events/messages to registered handlers.
 * How: maintains registrations and invokes listeners in deterministic order.
 * Used in framework: invoked by distributed components when observability dispatcher functionality is required.
 */
final class ObservabilityDispatcher
{
   /** @var array<int, ObservabilityHookInterface> */
   private array $hooks = [];

   /**
    * Handle register.
    *
    * @param ObservabilityHookInterface $hook
    * @return void
    */
   public function register(ObservabilityHookInterface $hook): void
   {
      $this->hooks[] = $hook;
   }

   /**
    * @param array<string, mixed> $attributes
    */
   public function emit(string $eventName, array $attributes = []): void
   {
      $event = new ObservabilityEvent($eventName, $attributes);
      foreach ($this->hooks as $hook) {
         $hook->onEvent($event);
      }
   }
}



