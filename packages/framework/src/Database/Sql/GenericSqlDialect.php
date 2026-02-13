<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Purpose: apply ANSI-style LIMIT/OFFSET behavior for generic SQL dialects.
 * How: appends clauses in deterministic order when values are provided.
 * Used in framework: default dialect and for mysql/mariadb/pgsql/sqlite.
 */
final class GenericSqlDialect implements SqlDialectInterface
{
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
   {
      $result = $sql;

      if ($limit !== null) {
         $result .= ' LIMIT ' . max(0, $limit);
      }
      if ($offset !== null) {
         $result .= ' OFFSET ' . max(0, $offset);
      }

      return $result;
   }
}

