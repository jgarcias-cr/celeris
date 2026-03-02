<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

use RuntimeException;

/**
 * Represent a domain-specific failure for View operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ViewException extends RuntimeException
{
   public static function templateNotFound(string $templatePath): self
   {
      return new self(sprintf('Template not found: %s', $templatePath));
   }

   public static function unsupportedEngine(string $engine): self
   {
      return new self(sprintf(
         'Unsupported template engine "%s". Supported engines: php, twig, plates, latte.',
         $engine
      ));
   }

   public static function missingDependency(string $engine, string $package, string $className): self
   {
      return new self(sprintf(
         'Template engine "%s" requires package "%s" (missing class %s).',
         $engine,
         $package,
         $className
      ));
   }

   public static function invalidRenderer(string $engine, string $details): self
   {
      return new self(sprintf('Template engine "%s" renderer is invalid: %s', $engine, $details));
   }
}

