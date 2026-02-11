<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Messaging;

/**
 * Purpose: implement in memory message bus behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when in memory message bus functionality is required.
 */
final class InMemoryMessageBus implements MessageBusInterface
{
   /** @var array<string, array<int, callable(MessageEnvelope): void>> */
   private array $subscribers = [];

   /** @var array<int, MessageEnvelope> */
   private array $history = [];

   /**
    * Handle publish.
    *
    * @param MessageEnvelope $envelope
    * @return void
    */
   public function publish(MessageEnvelope $envelope): void
   {
      $this->history[] = $envelope;

      $listeners = [
         ...($this->subscribers['*'] ?? []),
         ...($this->subscribers[$envelope->topic()] ?? []),
      ];

      foreach ($listeners as $listener) {
         $listener($envelope);
      }
   }

   /**
    * @param callable(MessageEnvelope): void $handler
    */
   public function subscribe(string $topic, callable $handler): void
   {
      $resolved = trim($topic);
      if ($resolved === '') {
         $resolved = '*';
      }

      $this->subscribers[$resolved] ??= [];
      $this->subscribers[$resolved][] = $handler;
   }

   /**
    * @return array<int, MessageEnvelope>
    */
   public function history(?string $topic = null): array
   {
      if ($topic === null) {
         return $this->history;
      }

      $filtered = [];
      foreach ($this->history as $message) {
         if ($message->topic() === $topic) {
            $filtered[] = $message;
         }
      }

      return $filtered;
   }
}



