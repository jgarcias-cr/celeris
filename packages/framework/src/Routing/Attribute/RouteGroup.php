<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Purpose: implement route group behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when route group functionality is required.
 */
final class RouteGroup
{
   /**
    * @param array<int, string> $middleware
    * @param array<int, string> $tags
    */
   public function __construct(
      public string $prefix = '',
      public array $middleware = [],
      public ?string $version = null,
      public array $tags = [],
      public string $namePrefix = '',
   ) {}
}



