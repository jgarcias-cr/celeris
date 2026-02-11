<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Connection;

use Celeris\Framework\Database\DatabaseConfig;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Database\DatabaseException;
use PDO;
use PDOException;

/**
 * Purpose: build configured pdo connection factory instances for runtime wiring.
 * How: encapsulates construction rules so callers avoid scattered wiring logic.
 * Used in framework: invoked by database components when pdo connection factory functionality is required.
 */
final class PdoConnectionFactory
{
   /**
    * Create a new instance.
    *
    * @param QueryTracerInterface $defaultTracer
    * @return mixed
    */
   public function __construct(private QueryTracerInterface $defaultTracer = new InMemoryQueryTracer())
   {
   }

   /**
    * Handle create.
    *
    * @param DatabaseConfig $config
    * @return PdoConnection
    */
   public function create(DatabaseConfig $config): PdoConnection
   {
      $dsn = $this->buildDsn($config);

      $options = [
         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ];

      foreach ($config->options() as $key => $value) {
         if (is_numeric($key)) {
            $options[(int) $key] = $value;
         }
      }

      try {
         $pdo = new PDO(
            $dsn,
            $config->username(),
            $config->password(),
            $options,
         );
      } catch (PDOException $exception) {
         throw new DatabaseException(
            sprintf('Failed to connect to database "%s": %s', $config->name(), $exception->getMessage()),
            (int) $exception->getCode(),
            $exception,
         );
      }

      return new PdoConnection($config->name(), $pdo, $this->defaultTracer);
   }

   /**
    * Handle build dsn.
    *
    * @param DatabaseConfig $config
    * @return string
    */
   public function buildDsn(DatabaseConfig $config): string
   {
      return match ($config->driver()) {
         DatabaseDriver::MySQL, DatabaseDriver::MariaDB => sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->host() ?? '127.0.0.1',
            $config->port() ?? 3306,
            $config->database() ?? '',
            $config->charset() ?? 'utf8mb4',
         ),
         DatabaseDriver::PostgreSQL => sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config->host() ?? '127.0.0.1',
            $config->port() ?? 5432,
            $config->database() ?? '',
         ),
         DatabaseDriver::SQLite => sprintf(
            'sqlite:%s',
            $config->path() ?? ($config->database() ?? ':memory:'),
         ),
         DatabaseDriver::SQLServer => sprintf(
            'sqlsrv:Server=%s,%d;Database=%s',
            $config->host() ?? '127.0.0.1',
            $config->port() ?? 1433,
            $config->database() ?? '',
         ),
      };
   }
}



