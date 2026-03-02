<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

use Celeris\Framework\Database\DatabaseException;

/**
 * Implement query trace inspector behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class QueryTraceInspector
{
   /**
    * Create a new instance.
    *
    * @param QueryTracerInterface $tracer
    * @return mixed
    */
   public function __construct(private QueryTracerInterface $tracer)
   {
   }

   /**
    * Handle snapshot.
    *
    * @return int
    */
   public function snapshot(): int
   {
      return count($this->tracer->all());
   }

   /**
    * @return array<int, QueryTraceEntry>
    */
   public function queriesSince(int $snapshot): array
   {
      $all = $this->tracer->all();
      if ($snapshot < 0 || $snapshot >= count($all)) {
         return array_slice($all, max(0, $snapshot));
      }

      return array_slice($all, $snapshot);
   }

   /**
    * Handle assert no queries since.
    *
    * @param int $snapshot
    * @param string $message
    * @return void
    */
   public function assertNoQueriesSince(int $snapshot, string $message = 'Hidden queries detected.'): void
   {
      if ($this->queriesSince($snapshot) !== []) {
         throw new DatabaseException($message);
      }
   }
}



