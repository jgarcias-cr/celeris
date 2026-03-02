<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Shape pagination SQL for Firebird compatibility.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class FirebirdSqlDialect implements SqlDialectInterface
{
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
   {
      if ($limit === null && $offset === null) {
         return $sql;
      }

      $safeLimit = $limit !== null ? max(0, $limit) : null;
      $safeOffset = $offset !== null ? max(0, $offset) : null;

      if ($safeLimit !== null && $safeOffset !== null) {
         $start = $safeOffset + 1;
         $end = $safeOffset + $safeLimit;
         return $sql . sprintf(' ROWS %d TO %d', $start, $end);
      }

      if ($safeLimit !== null) {
         return $sql . sprintf(' ROWS 1 TO %d', $safeLimit);
      }

      $start = ($safeOffset ?? 0) + 1;
      return $sql . sprintf(' ROWS %d TO 2147483647', $start);
   }
}

