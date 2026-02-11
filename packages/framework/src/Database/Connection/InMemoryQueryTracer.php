<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Purpose: implement in memory query tracer behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when in memory query tracer functionality is required.
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



