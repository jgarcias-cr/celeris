<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Intent\CacheIntentType;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Implement key invalidation strategy behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class KeyInvalidationStrategy implements InvalidationStrategyInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'key';
   }

   /**
    * Determine whether supports.
    *
    * @param CacheIntent $intent
    * @return bool
    */
   public function supports(CacheIntent $intent): bool
   {
      return $intent->type() === CacheIntentType::Invalidate && $intent->key() !== '*';
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
      $store->delete($intent->namespace(), $intent->key());
   }
}



