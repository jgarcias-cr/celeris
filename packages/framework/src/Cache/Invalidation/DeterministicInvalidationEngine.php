<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Invalidation;

use Celeris\Framework\Cache\Intent\CacheIntent;
use Celeris\Framework\Cache\Store\CacheStoreInterface;

/**
 * Orchestrate deterministic invalidation engine workflows within Cache.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DeterministicInvalidationEngine
{
   /** @var array<int, InvalidationStrategyInterface> */
   private array $strategies;

   /**
    * @param array<int, InvalidationStrategyInterface> $strategies
    */
   public function __construct(array $strategies = [])
   {
      if ($strategies === []) {
         $strategies = [
            new TagInvalidationStrategy(),
            new KeyInvalidationStrategy(),
            new NamespaceInvalidationStrategy(),
         ];
      }

      $this->strategies = array_values($strategies);
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
      foreach ($this->strategies as $strategy) {
         if (!$strategy->supports($intent)) {
            continue;
         }

         $strategy->invalidate($store, $intent);
      }
   }

   /**
    * @return array<int, InvalidationStrategyInterface>
    */
   public function strategies(): array
   {
      return $this->strategies;
   }
}



