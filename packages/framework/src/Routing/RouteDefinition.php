<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Purpose: implement route definition behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when route definition functionality is required.
 */
final class RouteDefinition
{
   /** @var array<int, string> */
   private array $methods;
   /** @var array<int, string> */
   private array $middleware;
   private string $path;

   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    */
   public function __construct(
      string|array $methods,
      string $path,
      private mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ) {
      $this->methods = self::normalizeMethods($methods);
      $this->path = self::normalizePath($path);
      $this->middleware = self::normalizeMiddleware($middleware);
      $this->metadata = $metadata ?? new RouteMetadata();
   }

   private RouteMetadata $metadata;

   /**
    * @return array<int, string>
    */
   public function methods(): array
   {
      return $this->methods;
   }

   /**
    * Handle path.
    *
    * @return string
    */
   public function path(): string
   {
      return $this->path;
   }

   /**
    * Handle handler.
    *
    * @return mixed
    */
   public function handler(): mixed
   {
      return $this->handler;
   }

   /**
    * @return array<int, string>
    */
   public function middleware(): array
   {
      return $this->middleware;
   }

   /**
    * Handle metadata.
    *
    * @return RouteMetadata
    */
   public function metadata(): RouteMetadata
   {
      return $this->metadata;
   }

   /**
    * @param array<int, string> $middleware
    */
   public function withMiddleware(array $middleware): self
   {
      $copy = clone $this;
      $copy->middleware = self::normalizeMiddleware($middleware);
      return $copy;
   }

   /**
    * Return a copy with the path.
    *
    * @param string $path
    * @return self
    */
   public function withPath(string $path): self
   {
      $copy = clone $this;
      $copy->path = self::normalizePath($path);
      return $copy;
   }

   /**
    * Return a copy with the metadata.
    *
    * @param RouteMetadata $metadata
    * @return self
    */
   public function withMetadata(RouteMetadata $metadata): self
   {
      $copy = clone $this;
      $copy->metadata = $metadata;
      return $copy;
   }

   /**
    * @param string|array<int, string> $methods
    * @return array<int, string>
    */
   private static function normalizeMethods(string|array $methods): array
   {
      $values = is_array($methods) ? $methods : [$methods];
      $normalized = array_map(static fn (mixed $method): string => strtoupper(trim((string) $method)), $values);
      $normalized = array_filter($normalized, static fn (string $method): bool => $method !== '');
      $normalized = array_values(array_unique($normalized));
      sort($normalized);
      return $normalized;
   }

   /**
    * Handle normalize path.
    *
    * @param string $path
    * @return string
    */
   private static function normalizePath(string $path): string
   {
      $trimmed = '/' . trim($path, '/');
      if ($trimmed === '//') {
         return '/';
      }

      return $trimmed === '/' ? '/' : rtrim($trimmed, '/');
   }

   /**
    * @param array<int, string> $middleware
    * @return array<int, string>
    */
   private static function normalizeMiddleware(array $middleware): array
   {
      $normalized = [];
      foreach ($middleware as $item) {
         $id = trim((string) $item);
         if ($id !== '') {
            $normalized[] = $id;
         }
      }

      return array_values($normalized);
   }
}




