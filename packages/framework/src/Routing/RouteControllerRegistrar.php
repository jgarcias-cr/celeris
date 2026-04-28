<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Provide a fluent controller-first route mapping API.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RouteControllerRegistrar
{
   /** @var array<int, string> */
   private array $middleware;

   /**
    * @param class-string $controller
    * @param array<int, string> $middleware
    */
   public function __construct(
      private RouteCollector $collector,
      private string $controller,
      private string $prefix = '',
      array $middleware = [],
   ) {
      $this->middleware = self::normalizeMiddleware($middleware);
      $this->prefix = self::normalizePrefix($prefix);
   }

   /**
    * Return a new registrar using the provided URI prefix.
    */
   public function prefix(string $prefix): self
   {
      $copy = clone $this;
      $copy->prefix = self::normalizePrefix($prefix);
      return $copy;
   }

   /**
    * Return a new registrar with merged middleware.
    *
    * @param array<int, string> $middleware
    */
   public function middleware(array $middleware): self
   {
      $copy = clone $this;
      $copy->middleware = self::normalizeMiddleware(array_merge($this->middleware, $middleware));
      return $copy;
   }

   /**
    * @param array<int, string> $middleware
    */
   public function get(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('GET', $path, $method, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function post(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('POST', $path, $method, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function put(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('PUT', $path, $method, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function patch(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('PATCH', $path, $method, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function delete(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('DELETE', $path, $method, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function options(string $path, string $method, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->map('OPTIONS', $path, $method, $middleware, $metadata);
   }

   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    */
   public function add(
      string|array $methods,
      string $path,
      string $method,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return $this->map($methods, $path, $method, $middleware, $metadata);
   }

   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    */
   private function map(
      string|array $methods,
      string $path,
      string $method,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      $mergedMiddleware = self::normalizeMiddleware(array_merge($this->middleware, $middleware));
      $handler = [$this->controller, $method];
      return $this->collector->add($methods, $this->composePath($path), $handler, $mergedMiddleware, $metadata);
   }

   /**
    * Compose the full route path from the configured prefix and route fragment.
    */
   private function composePath(string $path): string
   {
      $normalizedPath = '/' . trim($path, '/');
      if ($normalizedPath === '//') {
         $normalizedPath = '/';
      }

      if ($this->prefix === '') {
         return $normalizedPath;
      }

      if ($normalizedPath === '/') {
         return $this->prefix;
      }

      return $this->prefix . $normalizedPath;
   }

   /**
    * Normalize a route prefix so path composition stays consistent.
    */
   private static function normalizePrefix(string $prefix): string
   {
      $trimmed = trim($prefix);
      if ($trimmed === '') {
         return '';
      }

      return rtrim('/' . trim($trimmed, '/'), '/');
   }

   /**
    * @param array<int, string> $middleware
    * @return array<int, string>
    */
   private static function normalizeMiddleware(array $middleware): array
   {
      $normalized = [];
      foreach ($middleware as $entry) {
         $value = trim((string) $entry);
         if ($value !== '') {
            $normalized[] = $value;
         }
      }

      return array_values($normalized);
   }
}

