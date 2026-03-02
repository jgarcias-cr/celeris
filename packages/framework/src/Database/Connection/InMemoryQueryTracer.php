<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Implement in memory query tracer behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class InMemoryQueryTracer implements QueryTracerInterface
{
   /** @var array<int, QueryTraceEntry> */
   private array $entries = [];

   /**
    * Handle record.
    *
    * @param QueryTraceEntry $entry
    * @return void
    */
   public function record(QueryTraceEntry $entry): void
   {
      $this->entries[] = $entry;
   }

   /**
    * @return array<int, QueryTraceEntry>
    */
   public function all(): array
   {
      return $this->entries;
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->entries = [];
   }
}



