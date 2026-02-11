<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

/**
 * Purpose: define the contract for migration repository interface behavior in the Database subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete database services and resolved via dependency injection.
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



