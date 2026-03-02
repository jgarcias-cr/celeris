<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

use Celeris\Framework\Cache\CacheException;

/**
 * Implement file tag version state behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class FileTagVersionState implements TagVersionStateInterface
{
   /**
    * Create a new instance.
    *
    * @param string $path
    * @return mixed
    */
   public function __construct(private string $path)
   {
   }

   /**
    * Get the value.
    *
    * @param string $scope
    * @param string $tag
    * @return int
    */
   public function get(string $scope, string $tag): int
   {
      return $this->withState(function (array &$state) use ($scope, $tag): int {
         return (int) ($state[$scope][$tag] ?? 0);
      });
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
      return $this->withState(function (array &$state) use ($scope, $tag): int {
         $current = (int) ($state[$scope][$tag] ?? 0);
         $next = $current + 1;
         $state[$scope][$tag] = $next;
         return $next;
      });
   }

   /**
    * Handle clear scope.
    *
    * @param string $scope
    * @return void
    */
   public function clearScope(string $scope): void
   {
      $this->withState(function (array &$state) use ($scope): void {
         unset($state[$scope]);
      });
   }

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void
   {
      $this->withState(function (array &$state): void {
         $state = [];
      });
   }

   /**
    * @template T
    * @param callable(array<string, array<string, int>>&): T $callback
    * @return T
    */
   private function withState(callable $callback): mixed
   {
      $dir = dirname($this->path);
      if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
         throw new CacheException(sprintf('Cannot create cache state directory "%s".', $dir));
      }

      $handle = fopen($this->path, 'c+');
      if (!is_resource($handle)) {
         throw new CacheException(sprintf('Cannot open cache state file "%s".', $this->path));
      }

      try {
         if (!flock($handle, LOCK_EX)) {
            throw new CacheException('Cannot acquire lock for cache state file.');
         }

         $raw = stream_get_contents($handle);
         if (!is_string($raw)) {
            $raw = '';
         }

         /** @var array<string, array<string, int>> $state */
         $state = [];
         if (trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
               foreach ($decoded as $scope => $tags) {
                  if (!is_array($tags)) {
                     continue;
                  }

                  $state[(string) $scope] = [];
                  foreach ($tags as $tag => $version) {
                     $state[(string) $scope][(string) $tag] = (int) $version;
                  }
               }
            }
         }

         $result = $callback($state);

         rewind($handle);
         ftruncate($handle, 0);
         fwrite($handle, (string) json_encode($state, JSON_UNESCAPED_UNICODE));
         fflush($handle);

         return $result;
      } finally {
         flock($handle, LOCK_UN);
         fclose($handle);
      }
   }
}



