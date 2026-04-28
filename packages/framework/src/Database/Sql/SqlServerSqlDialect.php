<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Shape pagination SQL for SQL Server compatibility.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SqlServerSqlDialect implements SqlDialectInterface
{
   /**
    * Apply limit and offset clauses using this dialect's SQL syntax.
    */
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
   {
      if ($limit === null && $offset === null) {
         return $sql;
      }

      $safeLimit = $limit !== null ? max(0, $limit) : null;
      $safeOffset = $offset !== null ? max(0, $offset) : null;

      if ($safeOffset === null) {
         if ($safeLimit === null) {
            return $sql;
         }

         return (string) preg_replace('/^SELECT\s+/i', 'SELECT TOP ' . $safeLimit . ' ', $sql, 1);
      }

      $result = $sql;
      if (!$hasOrderBy) {
         $result .= ' ORDER BY (SELECT 1)';
      }

      $result .= ' OFFSET ' . $safeOffset . ' ROWS';
      if ($safeLimit !== null) {
         $result .= ' FETCH NEXT ' . $safeLimit . ' ROWS ONLY';
      }

      return $result;
   }
}

