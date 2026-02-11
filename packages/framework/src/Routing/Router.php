<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

use InvalidArgumentException;
use RuntimeException;

/**
 * Purpose: implement router behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when router functionality is required.
 */
final class Router
{
   /** @var array<string, array<string, RouterNode>> */
   private array $trees = [];
   /** @var array<int, RouteDefinition> */
   private array $routes = [];

   /**
    * Handle add route.
    *
    * @param RouteDefinition $route
    * @return void
    */
   public function addRoute(RouteDefinition $route): void
   {
      $versionKey = self::versionKey($route->metadata()->version());
      foreach ($route->methods() as $method) {
         $this->trees[$versionKey][$method] ??= new RouterNode();
         $this->insert($this->trees[$versionKey][$method], $route);
      }

      $this->routes[] = $route;
   }

   /**
    * Handle resolve.
    *
    * @param string $method
    * @param string $path
    * @param ?string $version
    * @return ?RouteMatch
    */
   public function resolve(string $method, string $path, ?string $version = null): ?RouteMatch
   {
      $normalizedMethod = strtoupper(trim($method));
      $segments = self::segments($path);

      foreach ($this->versionLookupOrder($version) as $versionKey) {
         $root = $this->trees[$versionKey][$normalizedMethod] ?? null;
         if ($root === null) {
            continue;
         }

         $match = $this->resolveFromNode($root, $segments, 0, []);
         if ($match !== null) {
            return $match;
         }
      }

      return null;
   }

   /**
    * @return array<int, string>
    */
   public function allowedMethods(string $path, ?string $version = null): array
   {
      $segments = self::segments($path);
      $allowed = [];

      foreach ($this->versionLookupOrder($version) as $versionKey) {
         foreach ($this->trees[$versionKey] ?? [] as $method => $root) {
            if ($this->resolveFromNode($root, $segments, 0, []) !== null) {
               $allowed[] = $method;
            }
         }
      }

      $allowed = array_values(array_unique($allowed));
      sort($allowed);
      return $allowed;
   }

   /**
    * @return array<int, RouteDefinition>
    */
   public function allRoutes(): array
   {
      return $this->routes;
   }

   /**
    * @return array<int, RouteDefinition>
    */
   public function routesForVersion(?string $version): array
   {
      $versionKey = self::versionKey($version);
      $filtered = [];
      foreach ($this->routes as $route) {
         if (self::versionKey($route->metadata()->version()) === $versionKey) {
            $filtered[] = $route;
         }
      }

      return $filtered;
   }

   /**
    * Determine whether has routes.
    *
    * @return bool
    */
   public function hasRoutes(): bool
   {
      return $this->routes !== [];
   }

   /**
    * Handle insert.
    *
    * @param RouterNode $root
    * @param RouteDefinition $route
    * @return void
    */
   private function insert(RouterNode $root, RouteDefinition $route): void
   {
      $segments = self::segments($route->path());
      $node = $root;

      foreach ($segments as $segment) {
         if (self::isParameterSegment($segment)) {
            $paramName = trim($segment, '{}');
            if ($paramName === '') {
               throw new InvalidArgumentException('Route parameter name cannot be empty.');
            }

            $node->parameterChild ??= new RouterNode();
            $node->parameterName ??= $paramName;
            $node = $node->parameterChild;
            continue;
         }

         $node->staticChildren[$segment] ??= new RouterNode();
         $node = $node->staticChildren[$segment];
      }

      if ($node->route !== null) {
         throw new RuntimeException(sprintf(
            'Duplicate route detected for "%s" [%s].',
            $route->path(),
            implode(',', $route->methods()),
         ));
      }

      $node->route = $route;
   }

   /**
    * @param array<int, string> $segments
    * @param array<string, string> $params
    */
   private function resolveFromNode(RouterNode $node, array $segments, int $index, array $params): ?RouteMatch
   {
      if ($index === count($segments)) {
         if ($node->route !== null) {
            return new RouteMatch($node->route, $params);
         }
         return null;
      }

      $segment = $segments[$index];

      if (isset($node->staticChildren[$segment])) {
         $match = $this->resolveFromNode($node->staticChildren[$segment], $segments, $index + 1, $params);
         if ($match !== null) {
            return $match;
         }
      }

      if ($node->parameterChild !== null && $node->parameterName !== null) {
         $newParams = $params;
         $newParams[$node->parameterName] = $segment;
         $match = $this->resolveFromNode($node->parameterChild, $segments, $index + 1, $newParams);
         if ($match !== null) {
            return $match;
         }
      }

      return null;
   }

   /**
    * @return array<int, string>
    */
   private function versionLookupOrder(?string $version): array
   {
      $order = [];
      if ($version !== null && $version !== '') {
         $order[] = self::versionKey($version);
      }
      $order[] = self::versionKey(null);

      return array_values(array_unique($order));
   }

   /**
    * @return array<int, string>
    */
   private static function segments(string $path): array
   {
      $normalized = '/' . trim($path, '/');
      if ($normalized === '/') {
         return [];
      }

      return array_values(array_filter(explode('/', trim($normalized, '/')), static fn (string $s): bool => $s !== ''));
   }

   /**
    * Determine whether is parameter segment.
    *
    * @param string $segment
    * @return bool
    */
   private static function isParameterSegment(string $segment): bool
   {
      return str_starts_with($segment, '{') && str_ends_with($segment, '}');
   }

   /**
    * Handle version key.
    *
    * @param ?string $version
    * @return string
    */
   private static function versionKey(?string $version): string
   {
      return $version !== null && trim($version) !== '' ? trim($version) : '__default__';
   }
}

/**
 * Purpose: implement router node behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when router node functionality is required.
 */
final class RouterNode
{
   /** @var array<string, RouterNode> */
   public array $staticChildren = [];
   public ?RouterNode $parameterChild = null;
   public ?string $parameterName = null;
   public ?RouteDefinition $route = null;
}




