<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseException;

/**
 * Purpose: implement migration runner behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when migration runner functionality is required.
 */
final class MigrationRunner
{
   /**
    * Create a new instance.
    *
    * @param ConnectionInterface $connection
    * @param MigrationRepositoryInterface $repository
    * @return mixed
    */
   public function __construct(
      private ConnectionInterface $connection,
      private MigrationRepositoryInterface $repository,
   ) {
   }

   /**
    * @param array<int, MigrationInterface> $migrations
    */
   public function migrate(array $migrations): MigrationResult
   {
      $this->repository->ensureStorage();

      $indexed = $this->indexMigrations($migrations);
      $applied = array_flip($this->repository->appliedVersions());

      $appliedNow = [];
      foreach ($indexed as $version => $migration) {
         if (isset($applied[$version])) {
            continue;
         }

         $this->connection->transactional(function (ConnectionInterface $connection) use ($migration, $version): void {
            $migration->up($connection);
            $this->repository->markApplied($version, $migration->description());
         });

         $appliedNow[] = $version;
      }

      return new MigrationResult($appliedNow, []);
   }

   /**
    * @param array<int, MigrationInterface> $migrations
    */
   public function rollback(array $migrations, int $steps = 1): MigrationResult
   {
      if ($steps <= 0) {
         return new MigrationResult([], []);
      }

      $this->repository->ensureStorage();
      $indexed = $this->indexMigrations($migrations);
      $applied = $this->repository->appliedVersions();
      rsort($applied);

      $target = array_slice($applied, 0, $steps);
      $rolledBack = [];

      foreach ($target as $version) {
         $migration = $indexed[$version] ?? null;
         if (!$migration instanceof MigrationInterface) {
            throw new DatabaseException(sprintf('Cannot rollback missing migration "%s".', $version));
         }

         $this->connection->transactional(function (ConnectionInterface $connection) use ($migration, $version): void {
            $migration->down($connection);
            $this->repository->markRolledBack($version);
         });

         $rolledBack[] = $version;
      }

      return new MigrationResult([], $rolledBack);
   }

   /**
    * @param array<int, MigrationInterface> $migrations
    * @return array<string, MigrationInterface>
    */
   private function indexMigrations(array $migrations): array
   {
      $indexed = [];
      foreach ($migrations as $migration) {
         $version = trim($migration->version());
         if ($version === '') {
            throw new DatabaseException('Migration version cannot be empty.');
         }
         if (isset($indexed[$version])) {
            throw new DatabaseException(sprintf('Duplicate migration version "%s".', $version));
         }

         $indexed[$version] = $migration;
      }

      ksort($indexed);
      return $indexed;
   }
}



