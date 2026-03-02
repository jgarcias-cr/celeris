<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Implement entity updating event behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class EntityUpdatingEvent extends AbstractPersistenceEvent
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'entity_updating';
   }
}



