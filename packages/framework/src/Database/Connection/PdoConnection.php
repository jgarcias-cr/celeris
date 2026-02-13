<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Database\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Purpose: implement pdo connection behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when pdo connection functionality is required.
 */
final class PdoConnection implements ConnectionInterface
{
   /**
    * Create a new instance.
    *
    * @param string $name
    * @param PDO $pdo
    * @param QueryTracerInterface $tracer
    * @param ?DatabaseDriver $driver
    * @param array<string, int|string|bool|null> $options
    * @return mixed
    */
   public function __construct(
      private string $name,
      private PDO $pdo,
      private QueryTracerInterface $tracer = new InMemoryQueryTracer(),
      private ?DatabaseDriver $driver = null,
      private array $options = [],
   ) {
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
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
    * Handle driver.
    *
    * @return ?DatabaseDriver
    */
   public function driver(): ?DatabaseDriver
   {
      return $this->driver;
   }

   /**
    * Handle options.
    *
    * @return array<string, int|string|bool|null>
    */
   public function options(): array
   {
      return $this->options;
   }

   /**
    * Handle option.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function option(string $key, mixed $default = null): mixed
   {
      return $this->options[$key] ?? $default;
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
      $successful = false;

      try {
         $statement = $this->prepareAndExecute($sql, $params);
         $successful = true;
         return $statement->rowCount();
      } catch (PDOException $exception) {
         throw new DatabaseException($exception->getMessage(), (int) $exception->getCode(), $exception);
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
         $statement = $this->prepareAndExecute($sql, $params);
         $rows = $statement->fetchAll();
         $successful = true;

         $normalized = [];
         foreach ($rows as $row) {
            if (is_array($row)) {
               $converted = [];
               foreach ($row as $key => $value) {
                  if (is_string($key)) {
                     $converted[$key] = $value;
                  }
               }
               $normalized[] = $converted;
            }
         }

         return $normalized;
      } catch (PDOException $exception) {
         throw new DatabaseException($exception->getMessage(), (int) $exception->getCode(), $exception);
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
      if (!$this->pdo->beginTransaction()) {
         throw new DatabaseException('Failed to begin transaction.');
      }
   }

   /**
    * Handle commit.
    *
    * @return void
    */
   public function commit(): void
   {
      if (!$this->pdo->commit()) {
         throw new DatabaseException('Failed to commit transaction.');
      }
   }

   /**
    * Handle roll back.
    *
    * @return void
    */
   public function rollBack(): void
   {
      if ($this->pdo->inTransaction() && !$this->pdo->rollBack()) {
         throw new DatabaseException('Failed to roll back transaction.');
      }
   }

   /**
    * Handle in transaction.
    *
    * @return bool
    */
   public function inTransaction(): bool
   {
      return $this->pdo->inTransaction();
   }

   /**
    * Handle last insert id.
    *
    * @return ?string
    */
   public function lastInsertId(): ?string
   {
      $id = $this->pdo->lastInsertId();
      return $id === false ? null : (string) $id;
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
    * @param array<string, mixed> $params
    */
   private function prepareAndExecute(string $sql, array $params): PDOStatement
   {
      $statement = $this->pdo->prepare($sql);
      if (!$statement instanceof PDOStatement) {
         throw new DatabaseException('Failed to prepare SQL statement.');
      }

      foreach ($params as $name => $value) {
         $placeholder = str_starts_with((string) $name, ':') ? (string) $name : ':' . (string) $name;
         $statement->bindValue($placeholder, $value);
      }

      $statement->execute();
      return $statement;
   }

   /**
    * @param array<string, mixed> $params
    */
   private function trace(string $sql, array $params, float $started, bool $successful): void
   {
      $entry = new QueryTraceEntry(
         $this->name,
         $sql,
         $params,
         $started,
         (microtime(true) - $started) * 1000,
         $successful,
      );

      $this->tracer->record($entry);
   }
}

