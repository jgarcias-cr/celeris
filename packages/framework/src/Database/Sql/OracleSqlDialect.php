<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Shape pagination SQL for Oracle compatibility.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class OracleSqlDialect implements SqlDialectInterface
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

