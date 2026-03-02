<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
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
      public string $prefix = '',
      public array $middleware = [],
      public ?string $version = null,
      public array $tags = [],
      public string $namePrefix = '',
   ) {}
}



