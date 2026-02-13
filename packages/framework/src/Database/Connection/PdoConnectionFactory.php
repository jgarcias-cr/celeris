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

      return new PdoConnection($config->name(), $pdo, $this->defaultTracer, $config->driver(), $config->options());
   }

   /**
    * Handle build dsn.
    *
    * @param DatabaseConfig $config
    * @return string
    */
   public function buildDsn(DatabaseConfig $config): string
   {
      if ($config->dsn() !== null && trim($config->dsn()) !== '') {
         return trim((string) $config->dsn());
      }

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
         DatabaseDriver::Firebird => $this->buildFirebirdDsn($config),
         DatabaseDriver::IBMDB2 => $this->buildIbmDsn($config),
         DatabaseDriver::Oracle => $this->buildOracleDsn($config),
      };
   }

   /**
    * Handle build firebird dsn.
    *
    * @param DatabaseConfig $config
    * @return string
    */
   private function buildFirebirdDsn(DatabaseConfig $config): string
   {
      $database = $config->database() ?? $config->path();
      if ($database === null || trim($database) === '') {
         throw new DatabaseException('Firebird DSN requires database or path when "dsn" is not provided.');
      }

      $charset = $config->charset() ?? 'UTF8';
      if ($config->host() !== null && trim($config->host()) !== '') {
         return sprintf(
            'firebird:dbname=%s/%d:%s;charset=%s',
            $config->host(),
            $config->port() ?? 3050,
            $database,
            $charset,
         );
      }

      return sprintf('firebird:dbname=%s;charset=%s', $database, $charset);
   }

   /**
    * Handle build ibm dsn.
    *
    * @param DatabaseConfig $config
    * @return string
    */
   private function buildIbmDsn(DatabaseConfig $config): string
   {
      $database = $config->database() ?? '';
      if ($config->host() !== null && trim($config->host()) !== '') {
         $protocol = $this->stringOption($config, 'protocol', 'TCPIP');

         return sprintf(
            'ibm:DATABASE=%s;HOSTNAME=%s;PORT=%d;PROTOCOL=%s;',
            $database,
            $config->host(),
            $config->port() ?? 50000,
            $protocol,
         );
      }

      if ($database === '') {
         throw new DatabaseException('IBM DB2 DSN requires database (and usually host/port) when "dsn" is not provided.');
      }

      return 'ibm:' . $database;
   }

   /**
    * Handle build oracle dsn.
    *
    * @param DatabaseConfig $config
    * @return string
    */
   private function buildOracleDsn(DatabaseConfig $config): string
   {
      $charset = $config->charset() ?? 'AL32UTF8';
      $host = $config->host();
      $serviceName = $this->stringOption($config, 'service_name', $config->database() ?? '');
      $sid = $this->stringOption($config, 'sid', '');

      if ($host !== null && trim($host) !== '') {
         $port = $config->port() ?? 1521;
         if ($sid !== '') {
            return sprintf('oci:dbname=%s:%d/%s;charset=%s', $host, $port, $sid, $charset);
         }

         if ($serviceName !== '') {
            return sprintf('oci:dbname=//%s:%d/%s;charset=%s', $host, $port, $serviceName, $charset);
         }
      }

      if ($serviceName !== '') {
         return sprintf('oci:dbname=%s;charset=%s', $serviceName, $charset);
      }

      throw new DatabaseException('Oracle DSN requires host + service_name/sid, database, or explicit "dsn".');
   }

   /**
    * Handle string option.
    *
    * @param DatabaseConfig $config
    * @param string $key
    * @param string $default
    * @return string
    */
   private function stringOption(DatabaseConfig $config, string $key, string $default = ''): string
   {
      $options = $config->options();
      $value = $options[$key] ?? null;
      if (!is_scalar($value)) {
         return $default;
      }

      $clean = trim((string) $value);
      return $clean !== '' ? $clean : $default;
   }
}
