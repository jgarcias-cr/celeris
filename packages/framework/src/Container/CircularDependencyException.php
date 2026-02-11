<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: represent a domain-specific failure for Container operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by container components and surfaced through kernel error handling.
 */
final class CircularDependencyException extends ContainerException
{
   /**
    * @param array<int, string> $path
    */
   public static function forPath(array $path): self
   {
      return new self(sprintf('Circular dependency detected: %s', implode(' -> ', $path)));
   }
}



