<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Messaging;

/**
 * Define the contract for message bus interface behavior in the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface MessageBusInterface
{
   /**
    * Handle publish.
    *
    * @param MessageEnvelope $envelope
    * @return void
    */
   public function publish(MessageEnvelope $envelope): void;

   /**
    * @param callable(MessageEnvelope): void $handler
    */
   public function subscribe(string $topic, callable $handler): void;

   /**
    * @return array<int, MessageEnvelope>
    */
   public function history(?string $topic = null): array;
}



