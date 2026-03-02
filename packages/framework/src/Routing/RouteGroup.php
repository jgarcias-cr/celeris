<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Implement route group behavior for the Routing subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RouteGroup
{
   /**
    * @param array<int, string> $middleware
    * @param array<int, string> $tags
    */
   public function __construct(
      private string $prefix = '',
      private array $middleware = [],
      private ?string $version = null,
      private array $tags = [],
      private string $namePrefix = '',
   ) {
      $this->prefix = self::normalizePrefix($prefix);
      $this->middleware = self::normalizeList($middleware);
      $this->tags = self::normalizeList($tags);
      $this->namePrefix = trim($namePrefix);
   }

   /**
    * Handle prefix.
    *
    * @return string
    */
   public function prefix(): string
   {
      return $this->prefix;
   }

   /**
    * @return array<int, string>
    */
   public function middleware(): array
   {
      return $this->middleware;
   }

   /**
    * Handle version.
    *
    * @return ?string
    */
   public function version(): ?string
   {
      return $this->version;
   }

   /**
    * @return array<int, string>
    */
   public function tags(): array
   {
      return $this->tags;
   }

   /**
    * Handle name prefix.
    *
    * @return string
    */
   public function namePrefix(): string
   {
      return $this->namePrefix;
   }

   /**
    * Handle merge.
    *
    * @param self $child
    * @return self
    */
   public function merge(self $child): self
   {
      return new self(
         self::joinPrefixes($this->prefix, $child->prefix),
         [...$this->middleware, ...$child->middleware],
         $child->version ?? $this->version,
         [...$this->tags, ...$child->tags],
         self::joinNamePrefix($this->namePrefix, $child->namePrefix),
      );
   }

   /**
    * Handle apply.
    *
    * @param RouteDefinition $route
    * @return RouteDefinition
    */
   public function apply(RouteDefinition $route): RouteDefinition
   {
      $routePath = $route->path();
      $combinedPath = self::joinPrefixes($this->prefix, $routePath);
      $middleware = [...$this->middleware, ...$route->middleware()];
      $metadata = $route->metadata();

      if ($this->version !== null && $metadata->version() === null) {
         $metadata = $metadata->withVersion($this->version);
      }
      if ($this->tags !== []) {
         $metadata = $metadata->withAddedTags($this->tags);
      }
      if ($this->namePrefix !== '') {
         $metadata = $metadata->withNamePrefix($this->namePrefix);
      }

      return $route
         ->withPath($combinedPath)
         ->withMiddleware($middleware)
         ->withMetadata($metadata);
   }

   /**
    * Handle normalize prefix.
    *
    * @param string $prefix
    * @return string
    */
   private static function normalizePrefix(string $prefix): string
   {
      $trimmed = trim($prefix);
      if ($trimmed === '' || $trimmed === '/') {
         return '';
      }

      return '/' . trim($trimmed, '/');
   }

   /**
    * Handle join prefixes.
    *
    * @param string $base
    * @param string $child
    * @return string
    */
   private static function joinPrefixes(string $base, string $child): string
   {
      $left = trim($base, '/');
      $right = trim($child, '/');

      if ($left === '' && $right === '') {
         return '/';
      }
      if ($left === '') {
         return '/' . $right;
      }
      if ($right === '') {
         return '/' . $left;
      }

      return '/' . $left . '/' . $right;
   }

   /**
    * Handle join name prefix.
    *
    * @param string $base
    * @param string $child
    * @return string
    */
   private static function joinNamePrefix(string $base, string $child): string
   {
      $left = trim($base, '.');
      $right = trim($child, '.');

      if ($left === '') {
         return $right;
      }
      if ($right === '') {
         return $left;
      }

      return $left . '.' . $right;
   }

   /**
    * @param array<int, string> $values
    * @return array<int, string>
    */
   private static function normalizeList(array $values): array
   {
      $normalized = [];
      foreach ($values as $value) {
         $clean = trim((string) $value);
         if ($clean !== '') {
            $normalized[] = $clean;
         }
      }

      return array_values($normalized);
   }
}




