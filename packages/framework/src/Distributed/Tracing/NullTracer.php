<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Purpose: implement null tracer behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when null tracer functionality is required.
 */
final class NullTracer implements TracerInterface
{
   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function startSpan(TraceContext $context, string $name, array $attributes = []): TraceSpan
   {
      return new TraceSpan($context, $name, $attributes);
   }

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function endSpan(TraceSpan $span, array $attributes = []): void
   {
      if (!$span->isEnded()) {
         $span->end($attributes);
      }
   }
}


