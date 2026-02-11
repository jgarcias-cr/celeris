<?php

declare(strict_types=1);

namespace Celeris\Framework\Config;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Config operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by config components and surfaced through kernel error handling.
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



