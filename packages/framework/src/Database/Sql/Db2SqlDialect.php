<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Shape pagination SQL for IBM DB2 compatibility.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Db2SqlDialect implements SqlDialectInterface
{
   /**
    * Apply limit and offset clauses using this dialect's SQL syntax.
    */
   public function applyLimitOffset(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
   {
      if ($limit === null && $offset === null) {
         return $sql;
      }

      $result = $sql;
      $safeLimit = $limit !== null ? max(0, $limit) : null;
      $safeOffset = $offset !== null ? max(0, $offset) : null;

      if ($safeOffset !== null) {
         $result .= ' OFFSET ' . $safeOffset . ' ROWS';
      }

      if ($safeLimit !== null) {
         if ($safeOffset === null) {
            $result .= ' FETCH FIRST ' . $safeLimit . ' ROWS ONLY';
         } else {
            $result .= ' FETCH NEXT ' . $safeLimit . ' ROWS ONLY';
         }
      }

      return $result;
   }
}

