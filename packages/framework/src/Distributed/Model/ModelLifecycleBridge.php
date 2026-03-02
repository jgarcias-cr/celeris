<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Model;

use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Database\ORM\Event\PersistenceEventInterface;
use Celeris\Framework\Distributed\Messaging\MessageBusInterface;
use Celeris\Framework\Distributed\Messaging\MessageEnvelope;
use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;

/**
 * Implement model lifecycle bridge behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ModelLifecycleBridge
{
   /**
    * Create a new instance.
    *
    * @param PersistenceEventDispatcher $persistenceEvents
    * @param MessageBusInterface $messageBus
    * @param string $topic
    * @param ?ObservabilityDispatcher $observability
    * @return mixed
    */
   public function __construct(
      private PersistenceEventDispatcher $persistenceEvents,
      private MessageBusInterface $messageBus,
      private string $topic = 'model.lifecycle',
      private ?ObservabilityDispatcher $observability = null,
   ) {
   }

   /**
    * Handle bind.
    *
    * @return void
    */
   public function bind(): void
   {
      $this->persistenceEvents->listen('*', function (PersistenceEventInterface $event): void {
         $lifecycleEvent = ModelLifecycleEvent::fromPersistenceEvent($event);

         $this->messageBus->publish(MessageEnvelope::create(
            $this->topic,
            'model.lifecycle.' . $lifecycleEvent->eventName(),
            $lifecycleEvent->toArray(),
            [
               'entity_class' => $lifecycleEvent->entityClass(),
            ]
         ));

         $this->observability?->emit('model.lifecycle', [
            'event' => $lifecycleEvent->eventName(),
            'entity_class' => $lifecycleEvent->entityClass(),
         ]);
      });
   }
}



