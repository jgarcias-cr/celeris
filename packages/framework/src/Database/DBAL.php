<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Query\QueryBuilder;
use Celeris\Framework\Database\Sql\SqlDialectResolver;

/**
 * Implement d b a l behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DBAL
{
   /**
    * Create a new instance.
    *
    * @param ConnectionPool $pool
    * @return mixed
    */
   public function __construct(private ConnectionPool $pool)
   {
   }

   /**
    * Handle connection.
    *
    * @param string $name
    * @return ConnectionInterface
    */
   public function connection(string $name = 'default'): ConnectionInterface
   {
      return $this->pool->get($name);
   }

   /**
    * Handle query builder.
    *
    * @param string $connection
    * @return QueryBuilder
    */
   public function queryBuilder(string $connection = 'default'): QueryBuilder
   {
      return new QueryBuilder(SqlDialectResolver::forConnection($this->connection($connection)));
   }

   /**
    * @template T
    * @param callable(ConnectionInterface): T $callback
    * @return T
    */
   public function transaction(callable $callback, string $connection = 'default'): mixed
   {
      return $this->connection($connection)->transactional($callback);
   }

   /**
    * Handle pool.
    *
    * @return ConnectionPool
    */
   public function pool(): ConnectionPool
   {
      return $this->pool;
   }
}


