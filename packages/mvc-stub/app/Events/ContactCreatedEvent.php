<?php

declare(strict_types=1);

namespace App\Events;

use Celeris\Framework\Domain\Event\AbstractDomainEvent;

/**
 * Domain/application event emitted after a contact is created.
 */
final class ContactCreatedEvent extends AbstractDomainEvent
{
   public function __construct(public readonly int $contactId)
   {
      parent::__construct();
   }

   public function payload(): array
   {
      return ['contact_id' => $this->contactId];
   }
}