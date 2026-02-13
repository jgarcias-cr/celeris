<?php

declare(strict_types=1);

namespace App\Events;

/**
 * Domain/application event emitted after a contact is created.
 *
 * This event keeps the payload intentionally small and can be extended
 * when your listeners need more context.
 */
final class ContactCreatedEvent
{
   public function __construct(public int $contactId) {}
}
