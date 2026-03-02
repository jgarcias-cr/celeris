<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Define the contract for query tracer interface behavior in the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



