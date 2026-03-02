<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Define dialect-specific SQL shaping rules used by query generation.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface SqlDialectInterface
{
   /**
    * Apply limit/offset semantics to a SELECT SQL statement.
    */
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string;
}

