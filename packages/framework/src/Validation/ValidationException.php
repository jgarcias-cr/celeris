<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Validation operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by validation components and surfaced through kernel error handling.
 */
final class ValidationException extends RuntimeException
{
   /**
    * @param array<int, array<string, string>> $errors
    */
   public function __construct(
      string $message = 'Validation failed.',
      private array $errors = [],
      private int $status = 422,
   ) {
      parent::__construct($message, $status);
   }

   /**
    * @return array<int, array<string, string>>
    */
   public function errors(): array
   {
      return $this->errors;
   }

   /**
    * Handle status.
    *
    * @return int
    */
   public function status(): int
   {
      return $this->status;
   }

   /**
    * Create an instance from result.
    *
    * @param ValidationResult $result
    * @param ?string $message
    * @param int $status
    * @return self
    */
   public static function fromResult(ValidationResult $result, ?string $message = null, int $status = 422): self
   {
      return new self($message ?? 'Validation failed.', $result->toArray(), $status);
   }
}



