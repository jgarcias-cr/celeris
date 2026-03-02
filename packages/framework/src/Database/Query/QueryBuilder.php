<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Query;

use Celeris\Framework\Database\DatabaseException;
use Celeris\Framework\Database\Sql\SqlDialectFactory;
use Celeris\Framework\Database\Sql\SqlDialectInterface;

/**
 * Compose query builder output from incremental inputs.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class QueryBuilder
{
   private SqlDialectInterface $dialect;
   private string $type = 'select';
   /** @var array<int, string> */
   private array $select = ['*'];
   private ?string $from = null;
   /** @var array<int, string> */
   private array $where = [];
   /** @var array<string, mixed> */
   private array $params = [];
   /** @var array<int, string> */
   private array $orderBy = [];
   private ?int $limit = null;
   private ?int $offset = null;

   private ?string $insertTable = null;
   /** @var array<string, mixed> */
   private array $insertData = [];

   private ?string $updateTable = null;
   /** @var array<string, mixed> */
   private array $updateData = [];

   private ?string $deleteTable = null;

   /**
    * @param ?SqlDialectInterface $dialect
    */
   public function __construct(?SqlDialectInterface $dialect = null)
   {
      $this->dialect = $dialect ?? SqlDialectFactory::generic();
   }

   /**
    * @param array<int, string>|string $columns
    */
   public function select(array|string $columns = ['*']): self
   {
      $this->type = 'select';
      $items = is_array($columns) ? $columns : [$columns];
      $normalized = [];
      foreach ($items as $column) {
         $clean = trim((string) $column);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }
      $this->select = $normalized !== [] ? $normalized : ['*'];
      return $this;
   }

   /**
    * Create an instance from value.
    *
    * @param string $table
    * @return self
    */
   public function from(string $table): self
   {
      $this->from = trim($table);
      return $this;
   }

   /**
    * @param array<string, mixed> $params
    */
   public function where(string $expression, array $params = []): self
   {
      $this->where = [trim($expression)];
      foreach ($params as $key => $value) {
         $this->params[(string) $key] = $value;
      }
      return $this;
   }

   /**
    * @param array<string, mixed> $params
    */
   public function andWhere(string $expression, array $params = []): self
   {
      $this->where[] = trim($expression);
      foreach ($params as $key => $value) {
         $this->params[(string) $key] = $value;
      }
      return $this;
   }

   /**
    * Handle order by.
    *
    * @param string $expression
    * @return self
    */
   public function orderBy(string $expression): self
   {
      $clean = trim($expression);
      if ($clean !== '') {
         $this->orderBy[] = $clean;
      }
      return $this;
   }

   /**
    * Handle limit.
    *
    * @param int $limit
    * @return self
    */
   public function limit(int $limit): self
   {
      $this->limit = max(0, $limit);
      return $this;
   }

   /**
    * Handle offset.
    *
    * @param int $offset
    * @return self
    */
   public function offset(int $offset): self
   {
      $this->offset = max(0, $offset);
      return $this;
   }

   /**
    * @param array<string, mixed> $data
    */
   public function insert(string $table, array $data): self
   {
      $this->type = 'insert';
      $this->insertTable = trim($table);
      $this->insertData = $this->normalizeData($data);
      return $this;
   }

   /**
    * @param array<string, mixed> $data
    */
   public function update(string $table, array $data): self
   {
      $this->type = 'update';
      $this->updateTable = trim($table);
      $this->updateData = $this->normalizeData($data);
      return $this;
   }

   /**
    * Handle delete.
    *
    * @param string $table
    * @return self
    */
   public function delete(string $table): self
   {
      $this->type = 'delete';
      $this->deleteTable = trim($table);
      return $this;
   }

   /**
    * Handle build.
    *
    * @return Query
    */
   public function build(): Query
   {
      return match ($this->type) {
         'select' => $this->buildSelect(),
         'insert' => $this->buildInsert(),
         'update' => $this->buildUpdate(),
         'delete' => $this->buildDelete(),
         default => throw new DatabaseException(sprintf('Unsupported query type "%s".', $this->type)),
      };
   }

   /**
    * Handle build select.
    *
    * @return Query
    */
   private function buildSelect(): Query
   {
      if ($this->from === null || $this->from === '') {
         throw new DatabaseException('SELECT query requires a FROM table.');
      }

      $sql = sprintf('SELECT %s FROM %s', implode(', ', $this->select), $this->from);
      if ($this->where !== []) {
         $sql .= ' WHERE ' . implode(' AND ', $this->where);
      }
      if ($this->orderBy !== []) {
         $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
      }
      $sql = $this->dialect->applyLimitOffset($sql, $this->limit, $this->offset, $this->orderBy !== []);

      return new Query($sql, $this->params);
   }

   /**
    * Handle build insert.
    *
    * @return Query
    */
   private function buildInsert(): Query
   {
      if ($this->insertTable === null || $this->insertTable === '') {
         throw new DatabaseException('INSERT query requires a table.');
      }
      if ($this->insertData === []) {
         throw new DatabaseException('INSERT query requires data.');
      }

      $columns = array_keys($this->insertData);
      $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

      $sql = sprintf(
         'INSERT INTO %s (%s) VALUES (%s)',
         $this->insertTable,
         implode(', ', $columns),
         implode(', ', $placeholders),
      );

      return new Query($sql, $this->insertData);
   }

   /**
    * Handle build update.
    *
    * @return Query
    */
   private function buildUpdate(): Query
   {
      if ($this->updateTable === null || $this->updateTable === '') {
         throw new DatabaseException('UPDATE query requires a table.');
      }
      if ($this->updateData === []) {
         throw new DatabaseException('UPDATE query requires data.');
      }

      $assignments = [];
      foreach (array_keys($this->updateData) as $column) {
         $assignments[] = sprintf('%s = :%s', $column, $column);
      }

      $sql = sprintf('UPDATE %s SET %s', $this->updateTable, implode(', ', $assignments));
      if ($this->where !== []) {
         $sql .= ' WHERE ' . implode(' AND ', $this->where);
      }

      return new Query($sql, [...$this->updateData, ...$this->params]);
   }

   /**
    * Handle build delete.
    *
    * @return Query
    */
   private function buildDelete(): Query
   {
      if ($this->deleteTable === null || $this->deleteTable === '') {
         throw new DatabaseException('DELETE query requires a table.');
      }

      $sql = sprintf('DELETE FROM %s', $this->deleteTable);
      if ($this->where !== []) {
         $sql .= ' WHERE ' . implode(' AND ', $this->where);
      }

      return new Query($sql, $this->params);
   }

   /**
    * @param array<string, mixed> $data
    * @return array<string, mixed>
    */
   private function normalizeData(array $data): array
   {
      $normalized = [];
      foreach ($data as $column => $value) {
         $clean = trim((string) $column);
         if ($clean === '') {
            continue;
         }
         $normalized[$clean] = $value;
      }

      if ($normalized !== []) {
         ksort($normalized);
      }

      return $normalized;
   }
}


