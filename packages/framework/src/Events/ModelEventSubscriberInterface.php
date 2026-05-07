<?php

declare(strict_types=1);

namespace Celeris\Framework\Events;

/**
 * Optional contract for listeners that only want selected model events.
 */
interface ModelEventSubscriberInterface extends ModelEventListenerInterface
{
   /**
    * @return array<int, string>
    */
   public static function subscribedEvents(): array;

   /**
    * @return array<int, class-string>
    */
   public static function subscribedModels(): array;
}
