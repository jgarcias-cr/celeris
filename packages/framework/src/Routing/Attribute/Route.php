<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * Purpose: implement route behavior for the Routing subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by routing components when route functionality is required.
 */
final class Route
{
   /**
    * @param string|array<int, string> $methods
    * @param array<int, string> $middleware
    * @param array<int, string> $tags
    */
   public function __construct(
      public string|array $methods = ['GET'],
      public string $path = '/',
      public ?string $name = null,
      public array $middleware = [],
      public ?string $summary = null,
      public ?string $description = null,
      public array $tags = [],
      public bool $deprecated = false,
      public ?string $version = null,
      public ?string $operationId = null,
   ) {}
}



