<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Exception;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordException;

/**
 * Purpose: capture Active Record validation failures before persistence is attempted.
 * How: stores a normalized error map keyed by property name so callers can build deterministic responses.
 * Used in framework: thrown by Active Record manager when metadata constraints or custom model rules fail.
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
