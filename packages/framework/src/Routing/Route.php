<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

use RuntimeException;

/**
 * Expose a static route registration API for ergonomic bootstrap wiring.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Route
{
   private static ?RouteCollector $collector = null;

   /**
    * Prevent direct instantiation of the static route facade.
    */
   private function __construct()
   {
   }

   /**
    * Bind the static route facade to a route collector instance.
    */
   public static function bind(RouteCollector $collector): void
   {
      self::$collector = $collector;
   }

   /**
    * Clear the currently bound route collector.
    */
   public static function clear(): void
   {
      self::$collector = null;
   }

   /**
    * Return the currently bound route collector.
    */
   public static function collector(): RouteCollector
   {
      if (self::$collector === null) {
         throw new RuntimeException('Route facade is not bound. Call Route::bind($kernel->routes()) during bootstrap.');
      }

      return self::$collector;
   }

   /**
    * @param class-string $controller
    * @param array<int, string> $middleware
    */
   public static function controller(string $controller, string $prefix = '', array $middleware = []): RouteControllerRegistrar
   {
      return self::collector()->controller($controller, $prefix, $middleware);
   }

   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    */
   public static function add(
      string|array $methods,
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->add($methods, $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function get(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->get($path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function post(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->post($path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function put(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->put($path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function patch(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->patch($path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function delete(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->delete($path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public static function options(
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      return self::collector()->options($path, $handler, $middleware, $metadata);
   }

   /**
    * @param callable(RouteCollector): void $callback
    */
   public static function group(RouteGroup $group, callable $callback): void
   {
      self::collector()->group($group, $callback);
   }
}

