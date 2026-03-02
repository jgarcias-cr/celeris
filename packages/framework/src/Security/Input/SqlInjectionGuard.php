<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Input;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Security\SecurityException;

/**
 * Implement sql injection guard behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class SqlInjectionGuard
{
   /** @var array<int, string> */
   private array $patterns = [
      '/\bunion\s+select\b/i',
      '/\b(select|insert|update|delete|drop|alter)\b.{0,32}\b(from|into|table|set)\b/i',
      '/\bor\s+1\s*=\s*1\b/i',
      '/(--|#|\/\*)/i',
   ];

   /**
    * Handle inspect.
    *
    * @param Request $request
    * @return void
    */
   public function inspect(Request $request): void
   {
      foreach ($this->extractScalars($request->getQueryParams()) as $candidate) {
         $this->assertSafe($candidate);
      }

      foreach ($this->extractScalars($request->getParsedBody()) as $candidate) {
         $this->assertSafe($candidate);
      }
   }

   /**
    * Handle assert safe.
    *
    * @param string $candidate
    * @return void
    */
   private function assertSafe(string $candidate): void
   {
      $value = trim($candidate);
      if ($value === '') {
         return;
      }

      foreach ($this->patterns as $pattern) {
         if (preg_match($pattern, $value) === 1) {
            throw new SecurityException('Potentially malicious SQL input detected.', 400);
         }
      }
   }

   /**
    * @return array<int, string>
    */
   private function extractScalars(mixed $value): array
   {
      if (is_string($value) || is_numeric($value) || is_bool($value)) {
         return [(string) $value];
      }
      if (!is_array($value)) {
         return [];
      }

      $flat = [];
      foreach ($value as $item) {
         $flat = [...$flat, ...$this->extractScalars($item)];
      }

      return $flat;
   }
}



