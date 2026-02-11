<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Purpose: define the contract for query tracer interface behavior in the Database subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete database services and resolved via dependency injection.
 */
interface QueryTracerInterface
{
   /**
    * Handle record.
    *
    * @param QueryTraceEntry $entry
    * @return void
    */
   public function record(QueryTraceEntry $entry): void;

   /**
    * @return array<int, QueryTraceEntry>
    */
   public function all(): array;

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void;
}



