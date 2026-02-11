<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Purpose: implement in memory tracer behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when in memory tracer functionality is required.
 */
final class InMemoryTracer implements TracerInterface
{
   /** @var array<int, TraceSpan> */
   private array $active = [];

   /** @var array<int, TraceSpan> */
   private array $finished = [];

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function startSpan(TraceContext $context, string $name, array $attributes = []): TraceSpan
   {
      $span = new TraceSpan($context, $name, $attributes);
      $this->active[] = $span;
      return $span;
   }

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function endSpan(TraceSpan $span, array $attributes = []): void
   {
      if (!$span->isEnded()) {
         $span->end($attributes);
      }

      $this->finished[] = $span;

      foreach ($this->active as $index => $candidate) {
         if ($candidate === $span) {
            unset($this->active[$index]);
            break;
         }
      }

      $this->active = array_values($this->active);
   }

   /**
    * @return array<int, TraceSpan>
    */
   public function activeSpans(): array
   {
      return $this->active;
   }

   /**
    * @return array<int, TraceSpan>
    */
   public function finishedSpans(): array
   {
      return $this->finished;
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->active = [];
      $this->finished = [];
   }
}



