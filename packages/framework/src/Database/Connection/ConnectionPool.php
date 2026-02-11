<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

use Celeris\Framework\Database\DatabaseConfig;
use Celeris\Framework\Database\DatabaseException;

/**
 * Purpose: implement connection pool behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when connection pool functionality is required.
 */
final class ConnectionPool
{
   /** @var array<string, DatabaseConfig> */
   private array $configs = [];
   /** @var array<string, ConnectionInterface> */
   private array $connections = [];

   /**
    * @param array<string, DatabaseConfig> $configs
    */
   public function __construct(
      array $configs = [],
      private ?PdoConnectionFactory $factory = null,
   ) {
      $this->factory = $factory ?? new PdoConnectionFactory();
      foreach ($configs as $name => $config) {
         $this->configs[$name] = $config;
      }
   }

   /**
    * Handle add config.
    *
    * @param DatabaseConfig $config
    * @return void
    */
   public function addConfig(DatabaseConfig $config): void
   {
      $this->configs[$config->name()] = $config;
      unset($this->connections[$config->name()]);
   }

   /**
    * Handle add connection.
    *
    * @param string $name
    * @param ConnectionInterface $connection
    * @return void
    */
   public function addConnection(string $name, ConnectionInterface $connection): void
   {
      $this->connections[$name] = $connection;
   }

   /**
    * Determine whether has.
    *
    * @param string $name
    * @return bool
    */
   public function has(string $name = 'default'): bool
   {
      return isset($this->connections[$name]) || isset($this->configs[$name]);
   }

   /**
    * Get the value.
    *
    * @param string $name
    * @return ConnectionInterface
    */
   public function get(string $name = 'default'): ConnectionInterface
   {
      if (isset($this->connections[$name])) {
         return $this->connections[$name];
      }

      $config = $this->configs[$name] ?? null;
      if ($config === null) {
         throw new DatabaseException(sprintf('Database connection "%s" is not configured.', $name));
      }

      $connection = $this->factory?->create($config);
      if (!$connection instanceof ConnectionInterface) {
         throw new DatabaseException(sprintf('Failed to build database connection "%s".', $name));
      }

      $this->connections[$name] = $connection;
      return $connection;
   }

   /**
    * Handle clear.
    *
    * @return void
    */
   public function clear(): void
   {
      $this->connections = [];
   }

   /**
    * @return array<int, string>
    */
   public function names(): array
   {
      $names = array_values(array_unique([
         ...array_keys($this->configs),
         ...array_keys($this->connections),
      ]));
      sort($names);
      return $names;
   }
}



