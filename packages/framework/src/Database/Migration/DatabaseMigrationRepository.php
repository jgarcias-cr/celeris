<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;

/**
 * Purpose: implement database migration repository behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when database migration repository functionality is required.
 */
final class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
   /**
    * Create a new instance.
    *
    * @param ConnectionInterface $connection
    * @param string $table
    * @return mixed
    */
   public function __construct(
      private ConnectionInterface $connection,
      private string $table = 'celeris_migrations',
   ) {
   }

   /**
    * Handle ensure storage.
    *
    * @return void
    */
   public function ensureStorage(): void
   {
      $this->connection->execute(sprintf(
         'CREATE TABLE IF NOT EXISTS %s (version VARCHAR(191) PRIMARY KEY, description VARCHAR(255) NOT NULL, applied_at BIGINT NOT NULL)',
         $this->table,
      ));
   }

   /**
    * Handle applied versions.
    *
    * @return array
    */
   public function appliedVersions(): array
   {
      $rows = $this->connection->fetchAll(sprintf('SELECT version FROM %s ORDER BY version ASC', $this->table));
      $versions = [];
      foreach ($rows as $row) {
         $version = $row['version'] ?? null;
         if (is_string($version) && trim($version) !== '') {
            $versions[] = $version;
         }
      }

      sort($versions);
      return $versions;
   }

   /**
    * Handle mark applied.
    *
    * @param string $version
    * @param string $description
    * @return void
    */
   public function markApplied(string $version, string $description): void
   {
      $this->connection->execute(
         sprintf('INSERT INTO %s (version, description, applied_at) VALUES (:version, :description, :applied_at)', $this->table),
         [
            'version' => $version,
            'description' => $description,
            'applied_at' => (int) floor(microtime(true) * 1000),
         ],
      );
   }

   /**
    * Handle mark rolled back.
    *
    * @param string $version
    * @return void
    */
   public function markRolledBack(string $version): void
   {
      $this->connection->execute(
         sprintf('DELETE FROM %s WHERE version = :version', $this->table),
         ['version' => $version],
      );
   }
}



