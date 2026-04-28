<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Architecture;

/**
 * Implement architecture violation behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ArchitectureViolation
{
   /**
    * Create a new instance.
    *
    * @param string $rule
    * @param string $message
    * @param string $severity
    * @return mixed
    */
   public function __construct(
      private string $rule,
      private string $message,
      private string $severity = 'error',
   ) {
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
    * Handle severity.
    *
    * @return string
    */
   public function severity(): string
   {
      return $this->severity;
   }

   /**
    * Convert the violation to an array representation.
    * @return array{rule:string,message:string,severity:string}
    */
   public function toArray(): array
   {
      return [
         'rule' => $this->rule,
         'message' => $this->message,
         'severity' => $this->severity,
      ];
   }
}



