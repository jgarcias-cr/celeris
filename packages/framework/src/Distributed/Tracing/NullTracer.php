<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Implement null tracer behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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


