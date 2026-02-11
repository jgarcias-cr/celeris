<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

/**
 * Purpose: define the contract for tag version state interface behavior in the Cache subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete cache services and resolved via dependency injection.
 */
interface TagVersionStateInterface
{
   /**
    * Get the value.
    *
    * @param string $scope
    * @param string $tag
    * @return int
    */
   public function get(string $scope, string $tag): int;

   /**
    * Handle bump.
    *
    * @param string $scope
    * @param string $tag
    * @return int
    */
   public function bump(string $scope, string $tag): int;

   /**
    * Handle clear scope.
    *
    * @param string $scope
    * @return void
    */
   public function clearScope(string $scope): void;

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void;
}



