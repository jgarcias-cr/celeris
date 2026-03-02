<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Exception;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordException;

/**
 * Capture Active Record validation failures before persistence is attempted.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ValidationFailedException extends ActiveRecordException
{
   /** @var array<string, array<int, string>> */
   private array $errors;

   /**
    * @param array<string, array<int, string>> $errors
    */
   public function __construct(string $message, array $errors)
   {
      parent::__construct($message);
      $this->errors = $errors;
   }

   /**
    * Return all normalized validation errors keyed by property.
    *
    * @return array<string, array<int, string>>
    */
   public function errors(): array
   {
      return $this->errors;
   }
}
