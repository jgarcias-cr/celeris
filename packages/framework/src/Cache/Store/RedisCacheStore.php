<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Store;

use Celeris\Framework\Cache\CacheEntry;
use Celeris\Framework\Cache\CacheException;

/**
 * Implement redis cache store behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RedisCacheStore implements CacheStoreInterface
{
   private object $redis;

   /**
    * Create a new instance.
    *
    * @param ?object $redisClient
    * @param string $prefix
    * @return mixed
    */
   public function __construct(
      ?object $redisClient = null,
      private string $prefix = 'celeris:cache:',
   ) {
      if ($redisClient !== null) {
         $this->redis = $redisClient;
         return;
      }

      if (!class_exists('Redis')) {
         throw new CacheException('Redis extension is not loaded.');
      }

      /** @var object $redis */
      $redis = new \Redis();
      $this->redis = $redis;
   }

   /**
    * Handle connect.
    *
    * @param string $host
    * @param int $port
    * @param float $timeout
    * @return void
    */
   public function connect(string $host = '127.0.0.1', int $port = 6379, float $timeout = 1.5): void
   {
      if (!method_exists($this->redis, 'connect')) {
         throw new CacheException('Provided Redis client does not support connect().');
      }

      $ok = $this->redis->connect($host, $port, $timeout);
      if ($ok !== true) {
         throw new CacheException(sprintf('Could not connect to Redis at %s:%d.', $host, $port));
      }
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
      $payload = $this->redis->get($this->entryKey($namespace, $key));
      if (!is_string($payload) || $payload === '') {
         return null;
      }

      $decoded = json_decode($payload, true);
      if (!is_array($decoded)) {
         return null;
      }

      $entry = new CacheEntry(
         $decoded['value'] ?? null,
         isset($decoded['expires_at']) && is_numeric($decoded['expires_at']) ? (float) $decoded['expires_at'] : null,
         is_array($decoded['tags'] ?? null) ? $decoded['tags'] : [],
         is_array($decoded['tag_versions'] ?? null) ? array_map(static fn (mixed $v): int => (int) $v, $decoded['tag_versions']) : [],
         is_string($decoded['etag'] ?? null) ? $decoded['etag'] : null,
      );

      if ($entry->isExpired()) {
         $this->delete($namespace, $key);
         return null;
      }

      foreach ($entry->tagVersions() as $tag => $version) {
         if ($this->getTagVersion($namespace, $tag) !== (int) $version) {
            $this->delete($namespace, $key);
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
      $payload = [
         'value' => $entry->value(),
         'expires_at' => $entry->expiresAt(),
         'tags' => $entry->tags(),
         'tag_versions' => $entry->tagVersions(),
         'etag' => $entry->etag(),
      ];

      $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
      $redisKey = $this->entryKey($namespace, $key);

      $ttlSeconds = null;
      if ($entry->expiresAt() !== null) {
         $ttlSeconds = max(1, (int) ceil($entry->expiresAt() - microtime(true)));
      }

      if ($ttlSeconds !== null && method_exists($this->redis, 'setex')) {
         $this->redis->setex($redisKey, $ttlSeconds, $json);
         return;
      }

      $this->redis->set($redisKey, $json);
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
      $this->redis->del($this->entryKey($namespace, $key));
   }

   /**
    * Handle clear namespace.
    *
    * @param string $namespace
    * @return void
    */
   public function clearNamespace(string $namespace): void
   {
      $pattern = $this->prefix . $namespace . ':*';
      if (!method_exists($this->redis, 'keys')) {
         throw new CacheException('Redis client does not support namespace clear without keys().');
      }

      $keys = $this->redis->keys($pattern);
      if (is_array($keys) && $keys !== []) {
         $this->redis->del(...$keys);
      }
   }

   /**
    * Handle clear all.
    *
    * @return void
    */
   public function clearAll(): void
   {
      if (!method_exists($this->redis, 'keys')) {
         throw new CacheException('Redis client does not support clearAll without keys().');
      }

      $keys = $this->redis->keys($this->prefix . '*');
      if (is_array($keys) && $keys !== []) {
         $this->redis->del(...$keys);
      }
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
      $value = $this->redis->get($this->tagKey($namespace, $tag));
      return is_numeric($value) ? (int) $value : 0;
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
      if (method_exists($this->redis, 'incr')) {
         return (int) $this->redis->incr($this->tagKey($namespace, $tag));
      }

      $next = $this->getTagVersion($namespace, $tag) + 1;
      $this->redis->set($this->tagKey($namespace, $tag), (string) $next);
      return $next;
   }

   /**
    * Handle entry key.
    *
    * @param string $namespace
    * @param string $key
    * @return string
    */
   private function entryKey(string $namespace, string $key): string
   {
      return $this->prefix . $namespace . ':' . $key;
   }

   /**
    * Handle tag key.
    *
    * @param string $namespace
    * @param string $tag
    * @return string
    */
   private function tagKey(string $namespace, string $tag): string
   {
      return $this->prefix . 'tags:' . $namespace . ':' . $tag;
   }
}



