<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

/**
 * Implement in memory tag version state behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class InMemoryTagVersionState implements TagVersionStateInterface
{
   /** @var array<string, array<string, int>> */
   private static array $versions = [];

   /**
    * Get the value.
    *
    * @param string $scope
    * @param string $tag
    * @return int
    */
   public function get(string $scope, string $tag): int
   {
      return self::$versions[$scope][$tag] ?? 0;
   }

   /**
    * Handle bump.
    *
    * @param string $scope
    * @param string $tag
    * @return int
    */
   public function bump(string $scope, string $tag): int
   {
      $current = self::$versions[$scope][$tag] ?? 0;
      $next = $current + 1;
      self::$versions[$scope][$tag] = $next;
      return $next;
   }

   /**
    * Handle clear scope.
    *
    * @param string $scope
    * @return void
    */
   public function clearScope(string $scope): void
   {
      unset(self::$versions[$scope]);
   }

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void
   {
      self::$versions = [];
   }
}



