<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

/**
 * Implement validation result behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ValidationResult
{
   /** @var array<int, ValidationError> */
   private array $errors = [];

   /**
    * Handle add error.
    *
    * @param ValidationError $error
    * @return void
    */
   public function addError(ValidationError $error): void
   {
      $this->errors[] = $error;
   }

   /**
    * Determine whether is valid.
    *
    * @return bool
    */
   public function isValid(): bool
   {
      return $this->errors === [];
   }

   /**
    * @return array<int, ValidationError>
    */
   public function errors(): array
   {
      return $this->errors;
   }

   /**
    * @return array<int, array<string, string>>
    */
   public function toArray(): array
   {
      $rows = array_map(
         static fn (ValidationError $error): array => $error->toArray(),
         $this->errors
      );

      usort($rows, static function (array $a, array $b): int {
         return [$a['path'], $a['rule'], $a['message']] <=> [$b['path'], $b['rule'], $b['message']];
      });

      return $rows;
   }
}



