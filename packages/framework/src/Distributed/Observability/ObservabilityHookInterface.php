<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Purpose: define the contract for observability hook interface behavior in the Distributed subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete distributed services and resolved via dependency injection.
 */
interface ObservabilityHookInterface
{
   /**
    * Handle on event.
    *
    * @param ObservabilityEvent $event
    * @return void
    */
   public function onEvent(ObservabilityEvent $event): void;
}



