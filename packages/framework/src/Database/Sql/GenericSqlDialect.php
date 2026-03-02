<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Apply ANSI-style LIMIT/OFFSET behavior for generic SQL dialects.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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

