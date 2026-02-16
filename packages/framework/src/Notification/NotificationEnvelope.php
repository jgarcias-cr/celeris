<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Purpose: implement notification envelope behavior for the Notification subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by notification components when notification envelope functionality is required.
 */
final class NotificationEnvelope
{
   /** @var array<string, mixed> */
   private array $metadata;

   /**
    * @param array<string, mixed> $metadata
    */
   public function __construct(
      private string $type,
      private mixed $payload,
      private ?string $channel = null,
      array $metadata = [],
   ) {
      $this->type = trim($this->type);
      $this->channel = self::normalizeChannel($this->channel);
      $this->metadata = $metadata;
   }

   /**
    * @param array<string, mixed> $metadata
    * @return self
    */
   public static function email(EmailMessage $message, ?string $channel = null, array $metadata = []): self
   {
      return new self('email', $message, $channel, $metadata);
   }

   /**
    * Handle type.
    *
    * @return string
    */
   public function type(): string
   {
      return $this->type;
   }

   /**
    * Handle payload.
    *
    * @return mixed
    */
   public function payload(): mixed
   {
      return $this->payload;
   }

   /**
    * Handle channel.
    *
    * @return ?string
    */
   public function channel(): ?string
   {
      return $this->channel;
   }

   /**
    * @return array<string, mixed>
    */
   public function metadata(): array
   {
      return $this->metadata;
   }

   /**
    * Handle email message.
    *
    * @return ?EmailMessage
    */
   public function emailMessage(): ?EmailMessage
   {
      return $this->payload instanceof EmailMessage ? $this->payload : null;
   }

   /**
    * Return a copy with the channel.
    *
    * @param ?string $channel
    * @return self
    */
   public function withChannel(?string $channel): self
   {
      $copy = clone $this;
      $copy->channel = self::normalizeChannel($channel);
      return $copy;
   }

   /**
    * @param ?string $channel
    * @return ?string
    */
   private static function normalizeChannel(?string $channel): ?string
   {
      if ($channel === null) {
         return null;
      }

      $clean = trim($channel);
      return $clean === '' ? null : $clean;
   }
}



