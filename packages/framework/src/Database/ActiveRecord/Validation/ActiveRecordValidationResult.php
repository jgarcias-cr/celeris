<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Validation;

/**
 * Carry normalized Active Record validation output.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ActiveRecordValidationResult
{
   /** @var array<string, array<int, string>> */
   private array $errors;

   /**
    * @param array<string, array<int, string>> $errors
    */
   public function __construct(array $errors = [])
   {
      $this->errors = [];
      foreach ($errors as $property => $messages) {
         $cleanProperty = trim((string) $property);
         if ($cleanProperty === '') {
            continue;
         }

         $normalized = [];
         foreach ((array) $messages as $message) {
            $text = trim((string) $message);
            if ($text !== '') {
               $normalized[] = $text;
            }
         }

         if ($normalized !== []) {
            $this->errors[$cleanProperty] = array_values(array_unique($normalized));
         }
      }
   }

   /**
    * Determine if validation produced no errors.
    *
    * @return bool
    */
   public function isValid(): bool
   {
      return $this->errors === [];
   }

   /**
    * Return all errors indexed by property name.
    *
    * @return array<string, array<int, string>>
    */
   public function errors(): array
   {
      return $this->errors;
   }
}
