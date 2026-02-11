<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache\Http;

/**
 * Purpose: implement http cache policy behavior for the Cache subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by cache components when http cache policy functionality is required.
 */
final class HttpCachePolicy
{
   /** @var array<int, string> */
   private array $vary;

   /**
    * @param array<int, string> $vary
    */
   public function __construct(
      private bool $public = true,
      private int $maxAge = 60,
      private ?int $staleWhileRevalidate = null,
      private bool $mustRevalidate = false,
      array $vary = ['accept', 'accept-encoding'],
   ) {
      $this->vary = self::normalizeVary($vary);
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
    * Handle max age.
    *
    * @return int
    */
   public function maxAge(): int
   {
      return $this->maxAge;
   }

   /**
    * Handle stale while revalidate.
    *
    * @return ?int
    */
   public function staleWhileRevalidate(): ?int
   {
      return $this->staleWhileRevalidate;
   }

   /**
    * Handle must revalidate.
    *
    * @return bool
    */
   public function mustRevalidate(): bool
   {
      return $this->mustRevalidate;
   }

   /**
    * @return array<int, string>
    */
   public function vary(): array
   {
      return $this->vary;
   }

   /**
    * Convert to cache control.
    *
    * @return string
    */
   public function toCacheControl(): string
   {
      $parts = [
         $this->public ? 'public' : 'private',
         'max-age=' . max(0, $this->maxAge),
      ];

      if ($this->staleWhileRevalidate !== null) {
         $parts[] = 'stale-while-revalidate=' . max(0, $this->staleWhileRevalidate);
      }
      if ($this->mustRevalidate) {
         $parts[] = 'must-revalidate';
      }

      return implode(', ', $parts);
   }

   /**
    * @param array<int, string> $vary
    * @return array<int, string>
    */
   private static function normalizeVary(array $vary): array
   {
      $normalized = [];
      foreach ($vary as $header) {
         $clean = strtolower(trim((string) $header));
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      $normalized = array_values(array_unique($normalized));
      sort($normalized);
      return $normalized;
   }
}



