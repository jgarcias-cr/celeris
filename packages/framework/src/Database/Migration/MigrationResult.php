<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

/**
 * Implement migration result behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class MigrationResult
{
   /** @var array<int, string> */
   private array $applied;
   /** @var array<int, string> */
   private array $rolledBack;

   /**
    * @param array<int, string> $applied
    * @param array<int, string> $rolledBack
    */
   public function __construct(array $applied = [], array $rolledBack = [])
   {
      $this->applied = $applied;
      $this->rolledBack = $rolledBack;
   }

   /**
    * @return array<int, string>
    */
   public function applied(): array
   {
      return $this->applied;
   }

   /**
    * @return array<int, string>
    */
   public function rolledBack(): array
   {
      return $this->rolledBack;
   }
}


