<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Messaging;

/**
 * Purpose: define the contract for message bus interface behavior in the Distributed subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete distributed services and resolved via dependency injection.
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



