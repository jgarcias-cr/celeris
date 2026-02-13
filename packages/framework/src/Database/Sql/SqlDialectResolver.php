<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\PdoConnection;
use Celeris\Framework\Database\DatabaseDriver;

/**
 * Purpose: resolve SQL dialects from runtime database connection instances.
 * How: inspects known connection implementations and falls back to generic dialect.
 * Used in framework: invoked by DBAL/ORM/ActiveRecord query builder creation.
 */
final class SqlDialectResolver
{
   public static function forConnection(ConnectionInterface $connection): SqlDialectInterface
   {
      if ($connection instanceof PdoConnection) {
         $driver = $connection->driver();
         if ($driver instanceof DatabaseDriver) {
            return SqlDialectFactory::forDriver($driver);
         }
      }

      return SqlDialectFactory::generic();
   }
}

