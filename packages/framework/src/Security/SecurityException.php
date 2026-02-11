<?php

declare(strict_types=1);

namespace Celeris\Framework\Security;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Security operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by security components and surfaced through kernel error handling.
 */
final class SecurityException extends RuntimeException
{
   /** @var array<string, string|array<int, string>> */
   private array $headers;

   /**
    * @param array<string, string|array<int, string>> $headers
    */
   public function __construct(string $message, private int $status = 403, array $headers = [])
   {
      parent::__construct($message, $status);
      $this->headers = $headers;
   }

   /**
    * Get the status.
    *
    * @return int
    */
   public function getStatus(): int
   {
      return $this->status;
   }

   /**
    * @return array<string, string|array<int, string>>
    */
   public function getHeaders(): array
   {
      return $this->headers;
   }
}




