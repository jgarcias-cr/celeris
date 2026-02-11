<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

/**
 * Purpose: model the allowed database driver values used by Database logic.
 * How: uses native enum cases to keep branching and serialization type-safe and explicit.
 * Used in framework: referenced by database logic, serialization, and guard conditions.
 */
enum DatabaseDriver: string
{
   case MySQL = 'mysql';
   case MariaDB = 'mariadb';
   case PostgreSQL = 'pgsql';
   case SQLite = 'sqlite';
   case SQLServer = 'sqlsrv';

   /**
    * Create an instance from string.
    *
    * @param string $driver
    * @return self
    */
   public static function fromString(string $driver): self
   {
      $normalized = strtolower(trim($driver));

      return match ($normalized) {
         'mysql' => self::MySQL,
         'mariadb' => self::MariaDB,
         'pgsql', 'postgres', 'postgresql' => self::PostgreSQL,
         'sqlite', 'sqlite3' => self::SQLite,
         'sqlsrv', 'mssql', 'sqlserver' => self::SQLServer,
         default => throw new \InvalidArgumentException(sprintf('Unsupported database driver "%s".', $driver)),
      };
   }
}



