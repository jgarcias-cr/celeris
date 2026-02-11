<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing;

/**
 * Purpose: implement route match behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when route match functionality is required.
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




