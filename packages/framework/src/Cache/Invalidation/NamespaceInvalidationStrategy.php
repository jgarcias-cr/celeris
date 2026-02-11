<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Intent\CacheIntentType;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Purpose: implement namespace invalidation strategy behavior for the Cache subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by cache components when namespace invalidation strategy functionality is required.
 */
final class NamespaceInvalidationStrategy implements InvalidationStrategyInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'namespace';
   }

   /**
    * Determine whether supports.
    *
    * @param CacheIntent $intent
    * @return bool
    */
   public function supports(CacheIntent $intent): bool
   {
      return $intent->type() === CacheIntentType::Invalidate
         && $intent->key() === '*'
         && $intent->tags() === [];
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
      $store->clearNamespace($intent->namespace());
   }
}



