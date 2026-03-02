<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

use Celeris\Framework\Cache\CacheEntry;

/**
 * Define the contract for cache store interface behavior in the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface CacheStoreInterface
{
   /**
    * Get the value.
    *
    * @param string $namespace
    * @param string $key
    * @return ?CacheEntry
    */
   public function get(string $namespace, string $key): ?CacheEntry;

   /**
    * Set the value.
    *
    * @param string $namespace
    * @param string $key
    * @param CacheEntry $entry
    * @return void
    */
   public function set(string $namespace, string $key, CacheEntry $entry): void;

   /**
    * Handle delete.
    *
    * @param string $namespace
    * @param string $key
    * @return void
    */
   public function delete(string $namespace, string $key): void;

   /**
    * Handle clear namespace.
    *
    * @param string $namespace
    * @return void
    */
   public function clearNamespace(string $namespace): void;

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void;

   /**
    * Get the tag version.
    *
    * @param string $namespace
    * @param string $tag
    * @return int
    */
   public function getTagVersion(string $namespace, string $tag): int;

   /**
    * Handle bump tag version.
    *
    * @param string $namespace
    * @param string $tag
    * @return int
    */
   public function bumpTagVersion(string $namespace, string $tag): int;
}



