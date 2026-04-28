<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Sql;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\PdoConnection;
use Celeris\Framework\Database\DatabaseDriver;

/**
 * Resolve SQL dialects from runtime database connection instances.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SqlDialectResolver
{
   /**
    * Resolve the SQL dialect for the given connection.
    */
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

