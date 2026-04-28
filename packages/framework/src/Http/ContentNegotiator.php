<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Resolves the best response media type from an `Accept` header.
 *
 * This helper is used when handlers can return multiple content types and need a
 * deterministic choice based on client preference and quality factors. It is
 * intentionally small and transport-focused: parsing/matching only, no response IO.
 */
final class ContentNegotiator
{
   /**
    * Negotiate content types.
    * @param array<int, string> $supportedTypes
    */
   public static function negotiate(array $supportedTypes, ?string $acceptHeader, ?string $default = null): ?string
   {
      if ($supportedTypes === []) {
         return $default;
      }

      $accept = trim((string) $acceptHeader);
      if ($accept === '' || $accept === '*/*') {
         return $supportedTypes[0] ?? $default;
      }

      $ranges = self::parseAcceptHeader($accept);
      foreach ($ranges as $range) {
         foreach ($supportedTypes as $candidate) {
            if (self::matches($range['type'], $candidate)) {
               return $candidate;
            }
         }
      }

      return $default;
   }


   /**
    * Handle accepts.
    *
    * @param string $contentType
    * @param ?string $acceptHeader
    * @return bool
    */
   public static function accepts(string $contentType, ?string $acceptHeader): bool
   {
      return self::negotiate([$contentType], $acceptHeader) !== null;
   }


   /**
    * Parse the `Accept` header into an ordered list of media types with quality factors and specificity.
    * @return array<int, array{type: string, q: float, specificity: int}>
    */
   private static function parseAcceptHeader(string $header): array
   {
      $items = [];
      foreach (explode(',', $header) as $part) {
         $part = trim($part);
         if ($part === '') {
            continue;
         }

         $segments = array_map('trim', explode(';', $part));
         $type = strtolower((string) array_shift($segments));
         $q = 1.0;

         foreach ($segments as $segment) {
            if (str_starts_with($segment, 'q=')) {
               $q = (float) substr($segment, 2);
            }
         }

         $items[] = [
            'type' => $type,
            'q' => $q,
            'specificity' => self::specificity($type),
         ];
      }

      usort(
         $items,
         static fn (array $a, array $b): int => ($b['q'] <=> $a['q']) ?: ($b['specificity'] <=> $a['specificity'])
      );

      return $items;
   }


   /**
    * Handle specificity.
    *
    * @param string $type
    * @return int
    */
   private static function specificity(string $type): int
   {
      if ($type === '*/*') {
         return 0;
      }
      if (str_ends_with($type, '/*')) {
         return 1;
      }
      return 2;
   }

   
   /**
    * Handle matches.
    *
    * @param string $range
    * @param string $candidate
    * @return bool
    */
   private static function matches(string $range, string $candidate): bool
   {
      $candidate = strtolower(trim(explode(';', $candidate, 2)[0]));
      $range = strtolower(trim(explode(';', $range, 2)[0]));

      if ($range === '*/*') {
         return true;
      }
      if ($range === $candidate) {
         return true;
      }

      [$rangeMain, $rangeSub] = array_pad(explode('/', $range, 2), 2, '*');
      [$candidateMain, $candidateSub] = array_pad(explode('/', $candidate, 2), 2, '*');

      if ($rangeMain === '*' && $rangeSub === '*') {
         return true;
      }

      return $rangeMain === $candidateMain && ($rangeSub === '*' || $rangeSub === $candidateSub);
   }
}



