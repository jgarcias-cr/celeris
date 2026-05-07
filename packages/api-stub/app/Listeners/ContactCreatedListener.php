<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContactCreatedEvent;
use Celeris\Framework\Domain\Event\DomainEventInterface;
use Celeris\Framework\Domain\Event\DomainEventListenerInterface;

final class ContactCreatedListener implements DomainEventListenerInterface
{

   /**
    * Handles the contact created event.
    *
    * @param DomainEventInterface $event The domain event.
    * @return void
    */
   public function handle(DomainEventInterface $event): void
   {
      if (!$event instanceof ContactCreatedEvent) {
         return;
      }

      // Your action here:
      // send email, audit log, sync CRM, enqueue job, etc.
   }
}
