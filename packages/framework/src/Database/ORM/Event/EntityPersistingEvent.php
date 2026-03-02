<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Event;

/**
 * Implement entity persisting event behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class EntityPersistingEvent extends AbstractPersistenceEvent
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'entity_persisting';
   }
}



