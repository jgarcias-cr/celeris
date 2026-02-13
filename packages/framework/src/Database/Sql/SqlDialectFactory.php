<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

use Celeris\Framework\Database\DatabaseDriver;

/**
 * Purpose: create SQL dialect implementations from database driver values.
 * How: maps driver enum cases to concrete dialect classes.
 * Used in framework: called by DBAL/ORM resolver paths.
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

