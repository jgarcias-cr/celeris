<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\ConnectionPool;
use Celeris\Framework\Database\Query\QueryBuilder;

/**
 * Purpose: implement d b a l behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when d b a l functionality is required.
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
    * @return QueryBuilder
    */
   public function queryBuilder(): QueryBuilder
   {
      return new QueryBuilder();
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



