<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Tracing;

/**
 * Define the contract for tracer interface behavior in the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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


