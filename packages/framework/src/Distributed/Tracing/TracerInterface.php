<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Purpose: define the contract for tracer interface behavior in the Distributed subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete distributed services and resolved via dependency injection.
 */
interface TracerInterface
{
   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function startSpan(TraceContext $context, string $name, array $attributes = []): TraceSpan;

   /**
    * @param array<string, scalar|array<int|string, scalar>|null> $attributes
    */
   public function endSpan(TraceSpan $span, array $attributes = []): void;
}


