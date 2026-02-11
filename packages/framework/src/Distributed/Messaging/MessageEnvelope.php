<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Messaging;

/**
 * Purpose: implement message envelope behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when message envelope functionality is required.
 */
final class MessageEnvelope
{
   /** @var array<string, scalar|array<int|string, scalar>|null> */
   private array $headers;

   /**
    * Create a new instance.
    *
    * @param string $id
    * @param string $topic
    * @param string $name
    * @param mixed $payload
    * @param array $headers
    * @param float $occurredAt
    * @return mixed
    */
   public function __construct(
      private string $id,
      private string $topic,
      private string $name,
      private mixed $payload,
      array $headers = [],
      private float $occurredAt = 0.0,
   ) {
      $this->headers = $headers;
      if ($this->occurredAt <= 0.0) {
         $this->occurredAt = microtime(true);
      }
   }

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $headers
    */
   public static function create(string $topic, string $name, mixed $payload, array $headers = []): self
   {
      return new self(bin2hex(random_bytes(8)), $topic, $name, $payload, $headers, microtime(true));
   }

   /**
    * Handle id.
    *
    * @return string
    */
   public function id(): string
   {
      return $this->id;
   }

   /**
    * Convert to pic.
    *
    * @return string
    */
   public function topic(): string
   {
      return $this->topic;
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return $this->name;
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
    * Handle occurred at.
    *
    * @return float
    */
   public function occurredAt(): float
   {
      return $this->occurredAt;
   }

   /**
    * @return array<string, scalar|array<int|string, scalar>|null>
    */
   public function headers(): array
   {
      return $this->headers;
   }

   /**
    * Handle header.
    *
    * @param string $name
    * @param mixed $default
    * @return mixed
    */
   public function header(string $name, mixed $default = null): mixed
   {
      return $this->headers[$name] ?? $default;
   }

   /**
    * Return a copy with the header.
    *
    * @param string $name
    * @param mixed $value
    * @return self
    */
   public function withHeader(string $name, mixed $value): self
   {
      $copy = clone $this;
      $copy->headers[$name] = $value;
      return $copy;
   }
}



