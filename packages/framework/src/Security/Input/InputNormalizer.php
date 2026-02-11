<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Input;

use Celeris\Framework\Http\Request;

/**
 * Purpose: implement input normalizer behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when input normalizer functionality is required.
 */
final class InputNormalizer
{
   /**
    * Handle normalize.
    *
    * @param Request $request
    * @return Request
    */
   public function normalize(Request $request): Request
   {
      $normalizedPath = $this->normalizePath($request->getPath());
      $normalizedQuery = $this->normalizeValue($request->getQueryParams());
      $normalizedParsedBody = $this->normalizeValue($request->getParsedBody());

      $normalized = $request
         ->withPath($normalizedPath)
         ->withQueryParams(is_array($normalizedQuery) ? $normalizedQuery : $request->getQueryParams())
         ->withParsedBody($normalizedParsedBody);

      return $normalized;
   }

   /**
    * Handle normalize path.
    *
    * @param string $path
    * @return string
    */
   private function normalizePath(string $path): string
   {
      $collapsed = preg_replace('#/{2,}#', '/', trim($path));
      if (!is_string($collapsed) || $collapsed === '') {
         return '/';
      }

      return str_starts_with($collapsed, '/') ? $collapsed : '/' . $collapsed;
   }

   /**
    * Handle normalize value.
    *
    * @param mixed $value
    * @return mixed
    */
   private function normalizeValue(mixed $value): mixed
   {
      if (is_array($value)) {
         $normalized = [];
         foreach ($value as $key => $item) {
            $normalized[$this->normalizeArrayKey($key)] = $this->normalizeValue($item);
         }

         return $normalized;
      }

      if (!is_string($value)) {
         return $value;
      }

      $trimmed = trim($value);
      return preg_replace('/[^\P{C}\n\t\r]/u', '', $trimmed) ?? $trimmed;
   }

   /**
    * Handle normalize array key.
    *
    * @param mixed $key
    * @return string|int
    */
   private function normalizeArrayKey(mixed $key): string|int
   {
      if (is_int($key)) {
         return $key;
      }

      $stringKey = trim((string) $key);
      return $stringKey;
   }
}



