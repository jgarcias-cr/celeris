<?php

declare(strict_types=1);

namespace App\Listeners\Models;

use App\Models\Contact;
use Celeris\Framework\Events\ModelEvent;
use Celeris\Framework\Events\ModelEventSubscriberInterface;

/**
 * Example autodiscovered listener for Contact model lifecycle events.
 */
final class ContactLifecycleListener implements ModelEventSubscriberInterface
{
   public static function subscribedEvents(): array
   {
      return [
         ModelEvent::CREATE,
         ModelEvent::UPDATE,
         ModelEvent::DELETE,
         ModelEvent::SHOW,
      ];
   }


   /**
    * Returns the list of models for which this listener should handle events.
    *
    * @return array<int, string>
    */
   public static function subscribedModels(): array
   {
      return [Contact::class];
   }


   /**
    * Handles model events for the Contact model. This method will be called for each subscribed event.
    *
    * @param ModelEvent $event The model event being handled.
    * @return void
    */
   public function handle(ModelEvent $event): void
   {
      // Add audit logging, notifications, cache invalidation, or queue dispatch here.
   }
}
