<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

/**
 * Model the allowed database driver values used by Database logic.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
enum DatabaseDriver: string
{
   case MySQL = 'mysql';
   case MariaDB = 'mariadb';
   case PostgreSQL = 'pgsql';
   case SQLite = 'sqlite';
   case SQLServer = 'sqlsrv';
   case Firebird = 'firebird';
   case IBMDB2 = 'ibm';
   case Oracle = 'oci';

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
         'firebird', 'ibase', 'interbase' => self::Firebird,
         'ibm', 'db2', 'ibmdb2' => self::IBMDB2,
         'oci', 'oracle', 'oci8' => self::Oracle,
         default => throw new \InvalidArgumentException(sprintf('Unsupported database driver "%s".', $driver)),
      };
   }
}


