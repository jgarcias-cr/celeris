<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Define the contract for observability hook interface behavior in the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



