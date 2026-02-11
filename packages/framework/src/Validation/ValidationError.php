<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

/**
 * Purpose: implement validation error behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when validation error functionality is required.
 */
final class ValidationError
{
   /**
    * Create a new instance.
    *
    * @param string $path
    * @param string $rule
    * @param string $message
    * @return mixed
    */
   public function __construct(
      private string $path,
      private string $rule,
      private string $message,
   ) {
   }

   /**
    * Handle path.
    *
    * @return string
    */
   public function path(): string
   {
      return $this->path;
   }

   /**
    * Handle rule.
    *
    * @return string
    */
   public function rule(): string
   {
      return $this->rule;
   }

   /**
    * Handle message.
    *
    * @return string
    */
   public function message(): string
   {
      return $this->message;
   }

   /**
    * @return array<string, string>
    */
   public function toArray(): array
   {
      return [
         'path' => $this->path,
         'rule' => $this->rule,
         'message' => $this->message,
      ];
   }
}



