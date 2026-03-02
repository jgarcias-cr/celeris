<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Cache\Invalidation\DeterministicInvalidationEngine;
use Celeris\Framework\Cache\Store\CacheStoreInterface;
use Celeris\Framework\Cache\Store\FileTagVersionState;
use Celeris\Framework\Cache\Store\InMemoryCacheStore;
use Celeris\Framework\Cache\Store\InMemoryTagVersionState;
use Celeris\Framework\Cache\Store\RedisCacheStore;

/**
 * Implement cache bootstrap behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class CacheBootstrap
{
   /**
    * Handle engine from config.
    *
    * @param ConfigRepository $config
    * @return CacheEngine
    */
   public static function engineFromConfig(ConfigRepository $config): CacheEngine
   {
      $store = self::storeFromConfig($config);
      $invalidation = new DeterministicInvalidationEngine();
      return new CacheEngine($store, $invalidation);
   }

   /**
    * Handle store from config.
    *
    * @param ConfigRepository $config
    * @return CacheStoreInterface
    */
   public static function storeFromConfig(ConfigRepository $config): CacheStoreInterface
   {
      $driver = strtolower((string) $config->get('cache.driver', 'memory'));

      if ($driver === 'redis') {
         $redis = new RedisCacheStore(prefix: (string) $config->get('cache.redis.prefix', 'celeris:cache:'));
         $redis->connect(
            (string) $config->get('cache.redis.host', '127.0.0.1'),
            (int) $config->get('cache.redis.port', 6379),
            (float) $config->get('cache.redis.timeout', 1.5),
         );

         return $redis;
      }

      $sharedInvalidation = (bool) $config->get('cache.memory.shared_invalidation', true);
      if ($sharedInvalidation) {
         $path = (string) $config->get('cache.memory.invalidation_file', '/tmp/celeris/cache-tag-versions.json');
         $state = new FileTagVersionState($path);
      } else {
         $state = new InMemoryTagVersionState();
      }

      return new InMemoryCacheStore($state, (string) $config->get('app.name', 'celeris'));
   }
}



