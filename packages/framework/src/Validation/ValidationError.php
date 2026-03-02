<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

/**
 * Implement validation error behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



