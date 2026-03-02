<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Define the contract for invalidation strategy interface behavior in the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface InvalidationStrategyInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string;

   /**
    * Determine whether supports.
    *
    * @param CacheIntent $intent
    * @return bool
    */
   public function supports(CacheIntent $intent): bool;

   /**
    * Handle invalidate.
    *
    * @param CacheStoreInterface $store
    * @param CacheIntent $intent
    * @return void
    */
   public function invalidate(CacheStoreInterface $store, CacheIntent $intent): void;
}



