<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Implement route collector behavior for the Routing subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RouteCollector
{
   /** @var array<int, RouteGroup> */
   private array $groupStack = [];

   /**
    * Create a new instance.
    *
    * @param Router $router
    * @return mixed
    */
   public function __construct(private Router $router)
   {
   }

   /**
    * Handle router.
    *
    * @return Router
    */
   public function router(): Router
   {
      return $this->router;
   }

   /**
    * Start fluent controller mapping while keeping route registration path unchanged.
    *
    * @param class-string $controller
    * @param array<int, string> $middleware
    */
   public function controller(string $controller, string $prefix = '', array $middleware = []): RouteControllerRegistrar
   {
      return new RouteControllerRegistrar($this, $controller, $prefix, $middleware);
   }

   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    */
   public function add(
      string|array $methods,
      string $path,
      mixed $handler,
      array $middleware = [],
      ?RouteMetadata $metadata = null,
   ): RouteDefinition {
      $route = new RouteDefinition($methods, $path, $handler, $middleware, $metadata);
      $group = $this->currentGroup();
      if ($group !== null) {
         $route = $group->apply($route);
      }

      $this->router->addRoute($route);
      return $route;
   }

   /**
    * @param array<int, string> $middleware
    */
   public function get(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('GET', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function post(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('POST', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function put(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('PUT', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function patch(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('PATCH', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function delete(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('DELETE', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param array<int, string> $middleware
    */
   public function options(string $path, mixed $handler, array $middleware = [], ?RouteMetadata $metadata = null): RouteDefinition
   {
      return $this->add('OPTIONS', $path, $handler, $middleware, $metadata);
   }

   /**
    * @param callable(RouteCollector): void $callback
    */
   public function group(RouteGroup $group, callable $callback): void
   {
      $parent = $this->currentGroup();
      $this->groupStack[] = $parent !== null ? $parent->merge($group) : $group;
      try {
         $callback($this);
      } finally {
         array_pop($this->groupStack);
      }
   }

   /**
    * Handle current group.
    *
    * @return ?RouteGroup
    */
   private function currentGroup(): ?RouteGroup
   {
      if ($this->groupStack === []) {
         return null;
      }

      return $this->groupStack[array_key_last($this->groupStack)];
   }
}



