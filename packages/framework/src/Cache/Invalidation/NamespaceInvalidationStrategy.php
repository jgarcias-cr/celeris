<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Intent\CacheIntentType;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Implement namespace invalidation strategy behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



