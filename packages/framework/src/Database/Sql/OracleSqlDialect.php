<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Purpose: shape pagination SQL for Oracle compatibility.
 * How: wraps source SQL with ROWNUM filtering to emulate limit/offset behavior.
 * Used in framework: selected when DatabaseDriver::Oracle is active.
 */
final class OracleSqlDialect implements SqlDialectInterface
{
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
   {
      if ($limit === null && $offset === null) {
         return $sql;
      }

      $safeLimit = $limit !== null ? max(0, $limit) : null;
      $safeOffset = $offset !== null ? max(0, $offset) : 0;

      if ($safeLimit === null) {
         return sprintf(
            'SELECT * FROM (SELECT celeris_qb_inner.*, ROWNUM celeris_rownum FROM (%s) celeris_qb_inner) WHERE celeris_rownum > %d',
            $sql,
            $safeOffset
         );
      }

      $maxRow = $safeOffset + $safeLimit;
      return sprintf(
         'SELECT * FROM (SELECT celeris_qb_inner.*, ROWNUM celeris_rownum FROM (%s) celeris_qb_inner WHERE ROWNUM <= %d) WHERE celeris_rownum > %d',
         $sql,
         $maxRow,
         $safeOffset
      );
   }
}

