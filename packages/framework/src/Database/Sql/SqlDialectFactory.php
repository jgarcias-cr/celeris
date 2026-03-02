<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

use Celeris\Framework\Database\DatabaseDriver;

/**
 * Create SQL dialect implementations from database driver values.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SqlDialectFactory
{
   public static function generic(): SqlDialectInterface
   {
      return new GenericSqlDialect();
   }

   public static function forDriver(DatabaseDriver $driver): SqlDialectInterface
   {
      return match ($driver) {
         DatabaseDriver::MySQL,
         DatabaseDriver::MariaDB,
         DatabaseDriver::PostgreSQL,
         DatabaseDriver::SQLite => new GenericSqlDialect(),
         DatabaseDriver::SQLServer => new SqlServerSqlDialect(),
         DatabaseDriver::Firebird => new FirebirdSqlDialect(),
         DatabaseDriver::IBMDB2 => new Db2SqlDialect(),
         DatabaseDriver::Oracle => new OracleSqlDialect(),
      };
   }
}

