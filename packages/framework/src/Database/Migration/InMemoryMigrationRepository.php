<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

/**
 * Implement in memory migration repository behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class InMemoryMigrationRepository implements MigrationRepositoryInterface
{
   /** @var array<string, string> */
   private array $applied = [];

   /**
    * Handle ensure storage.
    *
    * @return void
    */
   public function ensureStorage(): void
   {
      // No-op.
   }

   /**
    * Handle applied versions.
    *
    * @return array
    */
   public function appliedVersions(): array
   {
      $versions = array_keys($this->applied);
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
      $this->applied[$version] = $description;
   }

   /**
    * Handle mark rolled back.
    *
    * @param string $version
    * @return void
    */
   public function markRolledBack(string $version): void
   {
      unset($this->applied[$version]);
   }
}



