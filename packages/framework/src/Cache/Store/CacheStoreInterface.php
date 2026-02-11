<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

use Celeris\Framework\Cache\CacheEntry;

/**
 * Purpose: define the contract for cache store interface behavior in the Cache subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete cache services and resolved via dependency injection.
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



