<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

/**
 * Purpose: define the contract for connection interface behavior in the Database subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete database services and resolved via dependency injection.
 */
interface ConnectionInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string;

   /**
    * @param array<string, mixed> $params
    */
   public function execute(string $sql, array $params = []): int;

   /**
    * @param array<string, mixed> $params
    * @return array<int, array<string, mixed>>
    */
   public function fetchAll(string $sql, array $params = []): array;

   /**
    * @param array<string, mixed> $params
    * @return array<string, mixed>|null
    */
   public function fetchOne(string $sql, array $params = []): ?array;

   /**
    * Handle begin transaction.
    *
    * @return void
    */
   public function beginTransaction(): void;

   /**
    * Handle commit.
    *
    * @return void
    */
   public function commit(): void;

   /**
    * Handle roll back.
    *
    * @return void
    */
   public function rollBack(): void;

   /**
    * Handle in transaction.
    *
    * @return bool
    */
   public function inTransaction(): bool;

   /**
    * Handle last insert id.
    *
    * @return ?string
    */
   public function lastInsertId(): ?string;

   /**
    * Handle tracer.
    *
    * @return QueryTracerInterface
    */
   public function tracer(): QueryTracerInterface;

   /**
    * Set the tracer.
    *
    * @param QueryTracerInterface $tracer
    * @return void
    */
   public function setTracer(QueryTracerInterface $tracer): void;

   /**
    * @template T
    * @param callable(ConnectionInterface): T $callback
    * @return T
    */
   public function transactional(callable $callback): mixed;
}



