<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Represent a domain-specific failure for Container operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



