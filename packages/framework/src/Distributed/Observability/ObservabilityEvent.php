<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Observability;

/**
 * Implement observability event behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ObservabilityEvent
{
   /** @var array<string, mixed> */
   private array $attributes;

   /**
    * @param array<string, mixed> $attributes
    */
   public function __construct(
      private string $name,
      array $attributes = [],
      private float $occurredAt = 0.0,
   ) {
      $this->attributes = $attributes;
      if ($this->occurredAt <= 0.0) {
         $this->occurredAt = microtime(true);
      }
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
    * Handle occurred at.
    *
    * @return float
    */
   public function occurredAt(): float
   {
      return $this->occurredAt;
   }

   /**
    * @return array<string, mixed>
    */
   public function attributes(): array
   {
      return $this->attributes;
   }

   /**
    * Handle attribute.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function attribute(string $key, mixed $default = null): mixed
   {
      return $this->attributes[$key] ?? $default;
   }
}



