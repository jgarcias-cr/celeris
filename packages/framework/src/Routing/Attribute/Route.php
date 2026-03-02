<?php

declare(strict_types=1);

namespace Celeris\Framework\Routing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
/**
 * Implement route behavior for the Routing subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



