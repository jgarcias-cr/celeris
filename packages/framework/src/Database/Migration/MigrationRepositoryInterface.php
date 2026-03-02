<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

/**
 * Define the contract for migration repository interface behavior in the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface MigrationRepositoryInterface
{
   /**
    * Handle ensure storage.
    *
    * @return void
    */
   public function ensureStorage(): void;

   /**
    * @return array<int, string>
    */
   public function appliedVersions(): array;

   /**
    * Handle mark applied.
    *
    * @param string $version
    * @param string $description
    * @return void
    */
   public function markApplied(string $version, string $description): void;

   /**
    * Handle mark rolled back.
    *
    * @param string $version
    * @return void
    */
   public function markRolledBack(string $version): void;
}



