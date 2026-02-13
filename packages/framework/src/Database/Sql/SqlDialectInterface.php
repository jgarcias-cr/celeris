<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Purpose: define dialect-specific SQL shaping rules used by query generation.
 * How: declares typed hooks for pagination and driver-specific query suffix/prefix logic.
 * Used in framework: resolved per connection and injected into QueryBuilder.
 */
interface SqlDialectInterface
{
   /**
    * Apply limit/offset semantics to a SELECT SQL statement.
    */
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string;
}

