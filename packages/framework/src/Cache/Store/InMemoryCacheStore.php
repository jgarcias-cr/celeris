<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

use Celeris\Framework\Cache\CacheEntry;

/**
 * Purpose: implement in memory cache store behavior for the Cache subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by cache components when in memory cache store functionality is required.
 */
final class InMemoryCacheStore implements CacheStoreInterface
{
   /** @var array<string, array<string, CacheEntry>> */
   private array $entries = [];

   /**
    * Create a new instance.
    *
    * @param ?TagVersionStateInterface $tagState
    * @param string $stateScope
    * @return mixed
    */
   public function __construct(
      private ?TagVersionStateInterface $tagState = null,
      private string $stateScope = 'default',
   ) {
      $this->tagState = $tagState ?? new InMemoryTagVersionState();
   }

   /**
    * Get the value.
    *
    * @param string $namespace
    * @param string $key
    * @return ?CacheEntry
    */
   public function get(string $namespace, string $key): ?CacheEntry
   {
      $entry = $this->entries[$namespace][$key] ?? null;
      if (!$entry instanceof CacheEntry) {
         return null;
      }

      if ($entry->isExpired()) {
         unset($this->entries[$namespace][$key]);
         return null;
      }

      foreach ($entry->tagVersions() as $tag => $version) {
         if ($this->getTagVersion($namespace, $tag) !== (int) $version) {
            unset($this->entries[$namespace][$key]);
            return null;
         }
      }

      return $entry;
   }

   /**
    * Set the value.
    *
    * @param string $namespace
    * @param string $key
    * @param CacheEntry $entry
    * @return void
    */
   public function set(string $namespace, string $key, CacheEntry $entry): void
   {
      $this->entries[$namespace][$key] = $entry;
   }

   /**
    * Handle delete.
    *
    * @param string $namespace
    * @param string $key
    * @return void
    */
   public function delete(string $namespace, string $key): void
   {
      unset($this->entries[$namespace][$key]);
   }

   /**
    * Handle clear namespace.
    *
    * @param string $namespace
    * @return void
    */
   public function clearNamespace(string $namespace): void
   {
      unset($this->entries[$namespace]);
      $this->tagState?->clearScope($this->scope($namespace));
   }

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void
   {
      $this->entries = [];
      $this->tagState?->clearAll();
   }

   /**
    * Get the tag version.
    *
    * @param string $namespace
    * @param string $tag
    * @return int
    */
   public function getTagVersion(string $namespace, string $tag): int
   {
      return $this->tagState?->get($this->scope($namespace), $tag) ?? 0;
   }

   /**
    * Handle bump tag version.
    *
    * @param string $namespace
    * @param string $tag
    * @return int
    */
   public function bumpTagVersion(string $namespace, string $tag): int
   {
      return $this->tagState?->bump($this->scope($namespace), $tag) ?? 1;
   }

   /**
    * Handle scope.
    *
    * @param string $namespace
    * @return string
    */
   private function scope(string $namespace): string
   {
      return $this->stateScope . ':' . $namespace;
   }
}



