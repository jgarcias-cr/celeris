<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Intent;

/**
 * Implement cache intent behavior for the Cache subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class CacheIntent
{
   /** @var array<int, string> */
   private array $tags;

   /**
    * @param array<int, string> $tags
    */
   public function __construct(
      private CacheIntentType $type,
      private string $namespace,
      private string $key,
      private ?int $ttlSeconds = null,
      array $tags = [],
      private bool $allowStale = false,
      private bool $public = false,
      private ?int $staleWhileRevalidateSeconds = null,
   ) {
      $this->tags = self::normalizeTags($tags);
   }

   /**
    * Handle read.
    *
    * @param string $namespace
    * @param string $key
    * @param ?int $ttlSeconds
    * @param array $tags
    * @return self
    */
   public static function read(string $namespace, string $key, ?int $ttlSeconds = null, array $tags = []): self
   {
      return new self(CacheIntentType::ReadThrough, $namespace, $key, $ttlSeconds, $tags);
   }

   /**
    * Handle write.
    *
    * @param string $namespace
    * @param string $key
    * @param ?int $ttlSeconds
    * @param array $tags
    * @return self
    */
   public static function write(string $namespace, string $key, ?int $ttlSeconds = null, array $tags = []): self
   {
      return new self(CacheIntentType::WriteThrough, $namespace, $key, $ttlSeconds, $tags);
   }

   /**
    * Handle invalidate.
    *
    * @param string $namespace
    * @param string $key
    * @param array $tags
    * @return self
    */
   public static function invalidate(string $namespace, string $key = '*', array $tags = []): self
   {
      return new self(CacheIntentType::Invalidate, $namespace, $key, null, $tags);
   }

   /**
    * Handle type.
    *
    * @return CacheIntentType
    */
   public function type(): CacheIntentType
   {
      return $this->type;
   }

   public function namespace(): string
   {
      return $this->namespace;
   }

   /**
    * Handle key.
    *
    * @return string
    */
   public function key(): string
   {
      return $this->key;
   }

   /**
    * Handle ttl seconds.
    *
    * @return ?int
    */
   public function ttlSeconds(): ?int
   {
      return $this->ttlSeconds;
   }

   /**
    * @return array<int, string>
    */
   public function tags(): array
   {
      return $this->tags;
   }

   /**
    * Handle allow stale.
    *
    * @return bool
    */
   public function allowStale(): bool
   {
      return $this->allowStale;
   }

   /**
    * Determine whether is public.
    *
    * @return bool
    */
   public function isPublic(): bool
   {
      return $this->public;
   }

   /**
    * Handle stale while revalidate seconds.
    *
    * @return ?int
    */
   public function staleWhileRevalidateSeconds(): ?int
   {
      return $this->staleWhileRevalidateSeconds;
   }

   /**
    * Return a copy with the allow stale.
    *
    * @param bool $allowStale
    * @return self
    */
   public function withAllowStale(bool $allowStale = true): self
   {
      $copy = clone $this;
      $copy->allowStale = $allowStale;
      return $copy;
   }

   /**
    * Return a copy with the public.
    *
    * @param bool $public
    * @return self
    */
   public function withPublic(bool $public = true): self
   {
      $copy = clone $this;
      $copy->public = $public;
      return $copy;
   }

   /**
    * Return a copy with the stale while revalidate.
    *
    * @param ?int $seconds
    * @return self
    */
   public function withStaleWhileRevalidate(?int $seconds): self
   {
      $copy = clone $this;
      $copy->staleWhileRevalidateSeconds = $seconds !== null ? max(0, $seconds) : null;
      return $copy;
   }

   /**
    * Return a copy with the ttl.
    *
    * @param ?int $ttlSeconds
    * @return self
    */
   public function withTtl(?int $ttlSeconds): self
   {
      $copy = clone $this;
      $copy->ttlSeconds = $ttlSeconds !== null ? max(0, $ttlSeconds) : null;
      return $copy;
   }

   /**
    * @param array<int, string> $tags
    */
   public function withTags(array $tags): self
   {
      $copy = clone $this;
      $copy->tags = self::normalizeTags($tags);
      return $copy;
   }

   /**
    * @param array<int, string> $tags
    * @return array<int, string>
    */
   private static function normalizeTags(array $tags): array
   {
      $normalized = [];
      foreach ($tags as $tag) {
         $clean = trim((string) $tag);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      $normalized = array_values(array_unique($normalized));
      sort($normalized);
      return $normalized;
   }
}



