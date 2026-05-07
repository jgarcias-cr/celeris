<?php

declare(strict_types=1);

namespace Celeris\Framework\Events;

/**
 * Implement this interface in app listeners discovered by ModelEventManager.
 * The handle method will be called when a model event is fired.
 */
interface ModelEventListenerInterface
{

   /**
    * Handles the model event.
    *
    * @param ModelEvent $event The model event to handle.
    * @return void
    */
   public function handle(ModelEvent $event): void;
}
