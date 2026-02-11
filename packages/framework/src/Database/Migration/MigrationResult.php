<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\Migration;

/**
 * Purpose: implement migration result behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when migration result functionality is required.
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


