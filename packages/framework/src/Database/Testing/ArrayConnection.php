<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Testing;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\InMemoryQueryTracer;
use Celeris\Framework\Database\Connection\QueryTraceEntry;
use Celeris\Framework\Database\Connection\QueryTracerInterface;
use Celeris\Framework\Database\DatabaseException;

/**
 * Purpose: implement array connection behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when array connection functionality is required.
 */
final class ArrayConnection implements ConnectionInterface
{
   /** @var array<string, array<int, array<string, mixed>>> */
   private array $tables = [];
   /** @var array<string, int> */
   private array $autoIncrement = [];
   /** @var array<int, array{tables: array<string, array<int, array<string, mixed>>>, auto: array<string, int>, last: ?string}> */
   private array $transactionSnapshots = [];
   /** @var array<int, string> */
   private array $transactionLog = [];
   private ?string $lastInsertId = null;

   /**
    * Create a new instance.
    *
    * @param string $name
    * @param QueryTracerInterface $tracer
    * @return mixed
    */
   public function __construct(
      private string $name = 'array',
      private QueryTracerInterface $tracer = new InMemoryQueryTracer(),
   ) {
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return $this->name;
   }

   /**
    * Handle execute.
    *
    * @param string $sql
    * @param array $params
    * @return int
    */
   public function execute(string $sql, array $params = []): int
   {
      $started = microtime(true);
      $affected = 0;
      $successful = false;

      try {
         $normalized = trim($sql);

         if ($this->isCreateTable($normalized)) {
            $table = $this->extractTableFromCreate($normalized);
            $this->tables[$table] ??= [];
            $this->autoIncrement[$table] ??= 0;
            $successful = true;
            return 0;
         }

         if (preg_match('/^INSERT\s+INTO\s+([A-Za-z0-9_\.]+)\s*\((.+)\)\s*VALUES\s*\((.+)\)/i', $normalized, $matches) === 1) {
            $table = $this->cleanName($matches[1]);
            $columns = array_map('trim', explode(',', $matches[2]));

            $row = [];
            foreach ($columns as $column) {
               $cleanColumn = $this->cleanName($column);
               $paramKey = $cleanColumn;
               $row[$cleanColumn] = $params[$paramKey] ?? null;
            }

            if (!array_key_exists('id', $row) || $row['id'] === null || $row['id'] === '') {
               $this->autoIncrement[$table] = ($this->autoIncrement[$table] ?? 0) + 1;
               $row['id'] = $this->autoIncrement[$table];
            }

            $this->tables[$table] ??= [];
            $this->tables[$table][] = $row;
            $this->lastInsertId = (string) $row['id'];
            $successful = true;
            return 1;
         }

         if (preg_match('/^UPDATE\s+([A-Za-z0-9_\.]+)\s+SET\s+(.+)\s+WHERE\s+([A-Za-z0-9_\.]+)\s*=\s*:(\w+)/i', $normalized, $matches) === 1) {
            $table = $this->cleanName($matches[1]);
            $setClause = $matches[2];
            $whereColumn = $this->cleanName($matches[3]);
            $whereParam = $matches[4];
            $whereValue = $params[$whereParam] ?? null;

            $assignments = array_map('trim', explode(',', $setClause));
            $updates = [];
            foreach ($assignments as $assignment) {
               if (preg_match('/^([A-Za-z0-9_\.]+)\s*=\s*:(\w+)$/', $assignment, $setMatch) === 1) {
                  $updates[$this->cleanName($setMatch[1])] = $params[$setMatch[2]] ?? null;
               }
            }

            $affected = 0;
            foreach ($this->tables[$table] ?? [] as $index => $row) {
               if (($row[$whereColumn] ?? null) !== $whereValue) {
                  continue;
               }

               foreach ($updates as $column => $value) {
                  $row[$column] = $value;
               }
               $this->tables[$table][$index] = $row;
               $affected++;
            }

            $successful = true;
            return $affected;
         }

         if (preg_match('/^DELETE\s+FROM\s+([A-Za-z0-9_\.]+)\s+WHERE\s+([A-Za-z0-9_\.]+)\s*=\s*:(\w+)/i', $normalized, $matches) === 1) {
            $table = $this->cleanName($matches[1]);
            $whereColumn = $this->cleanName($matches[2]);
            $whereParam = $matches[3];
            $whereValue = $params[$whereParam] ?? null;

            $rows = $this->tables[$table] ?? [];
            $remaining = [];
            $affected = 0;
            foreach ($rows as $row) {
               if (($row[$whereColumn] ?? null) === $whereValue) {
                  $affected++;
                  continue;
               }
               $remaining[] = $row;
            }
            $this->tables[$table] = $remaining;
            $successful = true;
            return $affected;
         }

         throw new DatabaseException(sprintf('ArrayConnection does not support SQL: %s', $sql));
      } finally {
         $this->trace($sql, $params, $started, $successful);
      }
   }

   /**
    * Handle fetch all.
    *
    * @param string $sql
    * @param array $params
    * @return array
    */
   public function fetchAll(string $sql, array $params = []): array
   {
      $started = microtime(true);
      $successful = false;

      try {
         $normalized = trim($sql);

         if (preg_match('/^SELECT\s+(.+)\s+FROM\s+([A-Za-z0-9_\.]+)(?:\s+WHERE\s+([A-Za-z0-9_\.]+)\s*=\s*:(\w+))?(?:\s+ORDER\s+BY\s+([A-Za-z0-9_\.]+)\s+(ASC|DESC))?(?:\s+LIMIT\s+\d+)?/i', $normalized, $matches) === 1) {
            $columnSpec = trim($matches[1]);
            $table = $this->cleanName($matches[2]);
            $whereColumn = isset($matches[3]) ? $this->cleanName($matches[3]) : null;
            $whereParam = $matches[4] ?? null;
            $orderColumn = isset($matches[5]) ? $this->cleanName($matches[5]) : null;
            $orderDirection = strtoupper($matches[6] ?? 'ASC');

            $rows = $this->tables[$table] ?? [];

            if ($whereColumn !== null && $whereParam !== null) {
               $whereValue = $params[$whereParam] ?? null;
               $rows = array_values(array_filter(
                  $rows,
                  static fn (array $row): bool => ($row[$whereColumn] ?? null) === $whereValue,
               ));
            }

            if ($orderColumn !== null) {
               usort($rows, static function (array $a, array $b) use ($orderColumn, $orderDirection): int {
                  $cmp = ($a[$orderColumn] ?? null) <=> ($b[$orderColumn] ?? null);
                  return $orderDirection === 'DESC' ? -$cmp : $cmp;
               });
            }

            $selected = [];
            $columns = $this->parseColumns($columnSpec);
            foreach ($rows as $row) {
               if ($columns === ['*']) {
                  $selected[] = $row;
                  continue;
               }

               $filtered = [];
               foreach ($columns as $column) {
                  $filtered[$column] = $row[$column] ?? null;
               }
               $selected[] = $filtered;
            }

            $successful = true;
            return $selected;
         }

         throw new DatabaseException(sprintf('ArrayConnection does not support SQL: %s', $sql));
      } finally {
         $this->trace($sql, $params, $started, $successful);
      }
   }

   /**
    * Handle fetch one.
    *
    * @param string $sql
    * @param array $params
    * @return ?array
    */
   public function fetchOne(string $sql, array $params = []): ?array
   {
      $rows = $this->fetchAll($sql, $params);
      return $rows[0] ?? null;
   }

   /**
    * Handle begin transaction.
    *
    * @return void
    */
   public function beginTransaction(): void
   {
      $this->transactionLog[] = 'begin';
      $this->transactionSnapshots[] = [
         'tables' => $this->tables,
         'auto' => $this->autoIncrement,
         'last' => $this->lastInsertId,
      ];
   }

   /**
    * Handle commit.
    *
    * @return void
    */
   public function commit(): void
   {
      if ($this->transactionSnapshots === []) {
         throw new DatabaseException('No active transaction to commit.');
      }

      array_pop($this->transactionSnapshots);
      $this->transactionLog[] = 'commit';
   }

   /**
    * Handle roll back.
    *
    * @return void
    */
   public function rollBack(): void
   {
      if ($this->transactionSnapshots === []) {
         throw new DatabaseException('No active transaction to roll back.');
      }

      $snapshot = array_pop($this->transactionSnapshots);
      if (!is_array($snapshot)) {
         return;
      }

      $this->tables = $snapshot['tables'];
      $this->autoIncrement = $snapshot['auto'];
      $this->lastInsertId = $snapshot['last'];
      $this->transactionLog[] = 'rollback';
   }

   /**
    * Handle in transaction.
    *
    * @return bool
    */
   public function inTransaction(): bool
   {
      return $this->transactionSnapshots !== [];
   }

   /**
    * Handle last insert id.
    *
    * @return ?string
    */
   public function lastInsertId(): ?string
   {
      return $this->lastInsertId;
   }

   /**
    * Handle tracer.
    *
    * @return QueryTracerInterface
    */
   public function tracer(): QueryTracerInterface
   {
      return $this->tracer;
   }

   /**
    * Set the tracer.
    *
    * @param QueryTracerInterface $tracer
    * @return void
    */
   public function setTracer(QueryTracerInterface $tracer): void
   {
      $this->tracer = $tracer;
   }

   /**
    * Handle transactional.
    *
    * @param callable $callback
    * @return mixed
    */
   public function transactional(callable $callback): mixed
   {
      $started = !$this->inTransaction();
      if ($started) {
         $this->beginTransaction();
      }

      try {
         $result = $callback($this);
         if ($started) {
            $this->commit();
         }

         return $result;
      } catch (\Throwable $exception) {
         if ($started) {
            $this->rollBack();
         }
         throw $exception;
      }
   }

   /**
    * @return array<string, array<int, array<string, mixed>>>
    */
   public function tables(): array
   {
      return $this->tables;
   }

   /**
    * @return array<int, string>
    */
   public function transactionLog(): array
   {
      return $this->transactionLog;
   }

   /**
    * Determine whether is create table.
    *
    * @param string $sql
    * @return bool
    */
   private function isCreateTable(string $sql): bool
   {
      return preg_match('/^CREATE\s+TABLE/i', $sql) === 1;
   }

   /**
    * Handle extract table from create.
    *
    * @param string $sql
    * @return string
    */
   private function extractTableFromCreate(string $sql): string
   {
      if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([A-Za-z0-9_\.]+)/i', $sql, $matches) === 1) {
         return $this->cleanName($matches[1]);
      }

      throw new DatabaseException('Unable to parse CREATE TABLE statement.');
   }

   /**
    * @return array<int, string>
    */
   private function parseColumns(string $columnSpec): array
   {
      if ($columnSpec === '*') {
         return ['*'];
      }

      $columns = array_map(
         fn (string $column): string => $this->cleanName($column),
         array_map('trim', explode(',', $columnSpec))
      );

      return array_values(array_filter($columns, static fn (string $column): bool => $column !== ''));
   }

   /**
    * Handle clean name.
    *
    * @param string $name
    * @return string
    */
   private function cleanName(string $name): string
   {
      return trim(str_replace(['`', '"', '[', ']'], '', $name));
   }

   /**
    * @param array<string, mixed> $params
    */
   private function trace(string $sql, array $params, float $started, bool $successful): void
   {
      $this->tracer->record(new QueryTraceEntry(
         $this->name,
         $sql,
         $params,
         $started,
         (microtime(true) - $started) * 1000,
         $successful,
      ));
   }
}



