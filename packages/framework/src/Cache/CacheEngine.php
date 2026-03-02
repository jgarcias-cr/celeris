<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Intent\CacheIntentType;
use Celeris\Framework\Cache\Invalidation\DeterministicInvalidationEngine;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Orchestrate cache engine workflows within Cache.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class CacheEngine
{
   /**
    * Create a new instance.
    *
    * @param CacheStoreInterface $store
    * @param DeterministicInvalidationEngine $invalidation
    * @return mixed
    */
   public function __construct(
      private CacheStoreInterface $store,
      private DeterministicInvalidationEngine $invalidation,
   ) {
   }

   /**
    * Get the value.
    *
    * @param CacheIntent $intent
    * @return mixed
    */
   public function get(CacheIntent $intent): mixed
   {
      if ($intent->type() === CacheIntentType::Invalidate) {
         $this->invalidation->invalidate($this->store, $intent);
         return null;
      }

      $entry = $this->store->get($intent->namespace(), $intent->key());
      return $entry?->value();
   }

   /**
    * Get the entry.
    *
    * @param CacheIntent $intent
    * @return ?CacheEntry
    */
   public function getEntry(CacheIntent $intent): ?CacheEntry
   {
      if ($intent->type() === CacheIntentType::Invalidate) {
         $this->invalidation->invalidate($this->store, $intent);
         return null;
      }

      return $this->store->get($intent->namespace(), $intent->key());
   }

   /**
    * Handle put.
    *
    * @param CacheIntent $intent
    * @param mixed $value
    * @param ?string $etag
    * @return void
    */
   public function put(CacheIntent $intent, mixed $value, ?string $etag = null): void
   {
      if ($intent->type() === CacheIntentType::Invalidate) {
         $this->invalidation->invalidate($this->store, $intent);
         return;
      }

      $ttl = $intent->ttlSeconds();
      $expiresAt = $ttl !== null ? microtime(true) + max(0, $ttl) : null;

      $tagVersions = [];
      foreach ($intent->tags() as $tag) {
         $tagVersions[$tag] = $this->store->getTagVersion($intent->namespace(), $tag);
      }

      $entry = new CacheEntry(
         $value,
         $expiresAt,
         $intent->tags(),
         $tagVersions,
         $etag,
      );

      $this->store->set($intent->namespace(), $intent->key(), $entry);
   }

   /**
    * @param callable(): mixed $producer
    */
   public function remember(CacheIntent $intent, callable $producer): mixed
   {
      if ($intent->type() === CacheIntentType::Invalidate) {
         $this->invalidation->invalidate($this->store, $intent);
         return null;
      }

      $entry = $this->store->get($intent->namespace(), $intent->key());
      if ($entry instanceof CacheEntry) {
         return $entry->value();
      }

      $value = $producer();
      $etag = $this->etagFor($value);
      $this->put($intent, $value, $etag);
      return $value;
   }

   /**
    * Handle invalidate.
    *
    * @param CacheIntent $intent
    * @return void
    */
   public function invalidate(CacheIntent $intent): void
   {
      $invalidateIntent = $intent->type() === CacheIntentType::Invalidate
         ? $intent
         : CacheIntent::invalidate($intent->namespace(), $intent->key(), $intent->tags());

      $this->invalidation->invalidate($this->store, $invalidateIntent);
   }

   /**
    * Handle store.
    *
    * @return CacheStoreInterface
    */
   public function store(): CacheStoreInterface
   {
      return $this->store;
   }

   /**
    * Handle invalidation engine.
    *
    * @return DeterministicInvalidationEngine
    */
   public function invalidationEngine(): DeterministicInvalidationEngine
   {
      return $this->invalidation;
   }

   /**
    * Handle etag for.
    *
    * @param mixed $value
    * @return ?string
    */
   private function etagFor(mixed $value): ?string
   {
      if (is_resource($value)) {
         return null;
      }

      $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      if (!is_string($encoded)) {
         return null;
      }

      return 'W/"' . sha1($encoded) . '"';
   }
}



