<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

use RuntimeException;

/**
 * Represent a domain-specific failure for Config operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ConfigValidationException extends RuntimeException
{
   /**
    * @param array<int, string> $errors
    */
   public function __construct(private array $errors)
   {
      parent::__construct('Configuration validation failed: ' . implode('; ', $errors));
   }

   /**
    * @return array<int, string>
    */
   public function getErrors(): array
   {
      return $this->errors;
   }
}



