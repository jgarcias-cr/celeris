<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Intent\CacheIntentType;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Purpose: implement tag invalidation strategy behavior for the Cache subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by cache components when tag invalidation strategy functionality is required.
 */
final class TagInvalidationStrategy implements InvalidationStrategyInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'tags';
   }

   /**
    * Determine whether supports.
    *
    * @param CacheIntent $intent
    * @return bool
    */
   public function supports(CacheIntent $intent): bool
   {
      return $intent->type() === CacheIntentType::Invalidate && $intent->tags() !== [];
   }

   /**
    * Handle invalidate.
    *
    * @param CacheStoreInterface $store
    * @param CacheIntent $intent
    * @return void
    */
   public function invalidate(CacheStoreInterface $store, CacheIntent $intent): void
   {
      foreach ($intent->tags() as $tag) {
         $store->bumpTagVersion($intent->namespace(), $tag);
      }
   }
}



