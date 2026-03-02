<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Implement delivery result behavior for the Notification subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DeliveryResult
{
   /**
    * @param array<string, mixed> $metadata
    */
   public function __construct(
      private bool $delivered,
      private string $channel,
      private ?string $providerMessageId = null,
      private ?string $error = null,
      private array $metadata = [],
   ) {
   }

   /**
    * @param array<string, mixed> $metadata
    */
   public static function delivered(string $channel, ?string $providerMessageId = null, array $metadata = []): self
   {
      return new self(true, trim($channel), $providerMessageId, null, $metadata);
   }

   /**
    * @param array<string, mixed> $metadata
    */
   public static function failed(string $channel, string $error, array $metadata = [], ?string $providerMessageId = null): self
   {
      return new self(false, trim($channel), $providerMessageId, trim($error), $metadata);
   }

   /**
    * Determine whether is delivered.
    *
    * @return bool
    */
   public function isDelivered(): bool
   {
      return $this->delivered;
   }

   /**
    * Handle channel.
    *
    * @return string
    */
   public function channel(): string
   {
      return $this->channel;
   }

   /**
    * Handle provider message id.
    *
    * @return ?string
    */
   public function providerMessageId(): ?string
   {
      return $this->providerMessageId;
   }

   /**
    * Handle error.
    *
    * @return ?string
    */
   public function error(): ?string
   {
      return $this->error;
   }

   /**
    * @return array<string, mixed>
    */
   public function metadata(): array
   {
      return $this->metadata;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'delivered' => $this->delivered,
         'channel' => $this->channel,
         'provider_message_id' => $this->providerMessageId,
         'error' => $this->error,
         'metadata' => $this->metadata,
      ];
   }
}



