<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Implement trace span behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class TraceSpan
{
   /** @var array<string, scalar|array<int|string, scalar>|null> */
   private array $attributes;
   private float $startedAt;
   private ?float $endedAt = null;

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function __construct(
      private TraceContext $context,
      private string $name,
      array $attributes = [],
      ?float $startedAt = null,
   ) {
      $this->attributes = $attributes;
      $this->startedAt = $startedAt ?? microtime(true);
   }

   /**
    * Handle context.
    *
    * @return TraceContext
    */
   public function context(): TraceContext
   {
      return $this->context;
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
    * Handle started at.
    *
    * @return float
    */
   public function startedAt(): float
   {
      return $this->startedAt;
   }

   /**
    * Handle ended at.
    *
    * @return ?float
    */
   public function endedAt(): ?float
   {
      return $this->endedAt;
   }

   /**
    * Determine whether is ended.
    *
    * @return bool
    */
   public function isEnded(): bool
   {
      return $this->endedAt !== null;
   }

   /**
    * @return array<string, scalar|array<int|string, scalar>|null>
    */
   public function attributes(): array
   {
      return $this->attributes;
   }

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function end(array $attributes = []): void
   {
      foreach ($attributes as $key => $value) {
         $this->attributes[$key] = $value;
      }

      $this->endedAt = microtime(true);
   }

   /**
    * Handle duration ms.
    *
    * @return ?float
    */
   public function durationMs(): ?float
   {
      if ($this->endedAt === null) {
         return null;
      }

      return max(0.0, ($this->endedAt - $this->startedAt) * 1000);
   }
}



