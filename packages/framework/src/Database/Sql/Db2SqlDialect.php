<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

/**
 * Purpose: shape pagination SQL for IBM DB2 compatibility.
 * How: applies OFFSET/FETCH clauses using DB2 syntax variants.
 * Used in framework: selected when DatabaseDriver::IBMDB2 is active.
 */
final class Db2SqlDialect implements SqlDialectInterface
{
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

