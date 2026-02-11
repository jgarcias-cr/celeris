<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;

/**
 * Purpose: implement credential extractor behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when credential extractor functionality is required.
 */
final class CredentialExtractor
{
   /**
    * Handle bearer token.
    *
    * @param Request $request
    * @return ?string
    */
   public static function bearerToken(Request $request): ?string
   {
      $header = $request->getHeader('authorization');
      if ($header === null) {
         return null;
      }

      if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches)) {
         return null;
      }

      $token = trim($matches[1] ?? '');
      return $token !== '' ? $token : null;
   }

   /**
    * Handle api token.
    *
    * @param Request $request
    * @param string $headerName
    * @param string $queryParam
    * @return ?string
    */
   public static function apiToken(Request $request, string $headerName = 'x-api-key', string $queryParam = 'api_key'): ?string
   {
      $headerValue = $request->getHeader($headerName);
      if (is_string($headerValue) && trim($headerValue) !== '') {
         return trim($headerValue);
      }

      $queryValue = $request->getQueryParam($queryParam);
      if (is_string($queryValue) && trim($queryValue) !== '') {
         return trim($queryValue);
      }

      return null;
   }
}



