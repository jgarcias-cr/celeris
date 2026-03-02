<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Implement route match behavior for the Routing subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RouteMatch
{
   /**
    * @param array<string, string> $params
    */
   public function __construct(
      private RouteDefinition $route,
      private array $params = [],
   ) {}

   /**
    * Get the route.
    *
    * @return RouteDefinition
    */
   public function getRoute(): RouteDefinition
   {
      return $this->route;
   }

   /**
    * @return array<string, string>
    */
   public function getParams(): array
   {
      return $this->params;
   }
}




