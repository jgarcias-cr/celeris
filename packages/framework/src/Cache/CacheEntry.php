<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache;

/**
 * Purpose: implement cache entry behavior for the Cache subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by cache components when cache entry functionality is required.
 */
final class CacheEntry
{
   /** @var array<int, string> */
   private array $tags;
   /** @var array<string, int> */
   private array $tagVersions;

   /**
    * @param array<int, string> $tags
    * @param array<string, int> $tagVersions
    */
   public function __construct(
      private mixed $value,
      private ?float $expiresAt,
      array $tags = [],
      array $tagVersions = [],
      private ?string $etag = null,
   ) {
      $this->tags = self::normalizeTags($tags);
      $this->tagVersions = $tagVersions;
      ksort($this->tagVersions);
   }

   /**
    * Handle value.
    *
    * @return mixed
    */
   public function value(): mixed
   {
      return $this->value;
   }

   /**
    * Handle expires at.
    *
    * @return ?float
    */
   public function expiresAt(): ?float
   {
      return $this->expiresAt;
   }

   /**
    * Determine whether is expired.
    *
    * @param ?float $now
    * @return bool
    */
   public function isExpired(?float $now = null): bool
   {
      if ($this->expiresAt === null) {
         return false;
      }

      return ($now ?? microtime(true)) >= $this->expiresAt;
   }

   /**
    * @return array<int, string>
    */
   public function tags(): array
   {
      return $this->tags;
   }

   /**
    * @return array<string, int>
    */
   public function tagVersions(): array
   {
      return $this->tagVersions;
   }

   /**
    * Handle etag.
    *
    * @return ?string
    */
   public function etag(): ?string
   {
      return $this->etag;
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



