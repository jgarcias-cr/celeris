<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Purpose: implement query trace entry behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when query trace entry functionality is required.
 */
final class QueryTraceEntry
{
   /** @var array<string, mixed> */
   private array $params;

   /**
    * @param array<string, mixed> $params
    */
   public function __construct(
      private string $connection,
      private string $sql,
      array $params,
      private float $startedAt,
      private float $durationMs,
      private bool $successful,
   ) {
      $this->params = $params;
   }

   /**
    * Handle connection.
    *
    * @return string
    */
   public function connection(): string
   {
      return $this->connection;
   }

   /**
    * Handle sql.
    *
    * @return string
    */
   public function sql(): string
   {
      return $this->sql;
   }

   /**
    * @return array<string, mixed>
    */
   public function params(): array
   {
      return $this->params;
   }

   /**
    * Handle started at.
    *
    * @return float
    */
   public function startedAt(): float
   {
      return $this->startedAt;
   }

   /**
    * Handle duration ms.
    *
    * @return float
    */
   public function durationMs(): float
   {
      return $this->durationMs;
   }

   /**
    * Handle successful.
    *
    * @return bool
    */
   public function successful(): bool
   {
      return $this->successful;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'connection' => $this->connection,
         'sql' => $this->sql,
         'params' => $this->params,
         'started_at' => $this->startedAt,
         'duration_ms' => $this->durationMs,
         'successful' => $this->successful,
      ];
   }
}



