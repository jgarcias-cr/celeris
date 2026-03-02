<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Route observability dispatcher events/messages to registered handlers.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



