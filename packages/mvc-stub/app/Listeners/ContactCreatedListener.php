<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContactCreatedEvent;
use Celeris\Framework\Domain\Event\DomainEventInterface;
use Celeris\Framework\Domain\Event\DomainEventListenerInterface;
use Celeris\Framework\Logging\LoggerInterface;

final class ContactCreatedListener implements DomainEventListenerInterface
{
   public function __construct(private LoggerInterface $logger) {}

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

      $this->logger->info('ContactCreatedEvent handled after contact add.', [
         'listener' => 'contact-domain-event',
         'contact_id' => $event->contactId,
         'event_id' => $event->eventId(),
      ]);
   }
}
