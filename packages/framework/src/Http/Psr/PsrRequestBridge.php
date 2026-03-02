<?php

declare(strict_types=1);

namespace Celeris\Framework\Http\Psr;

use Celeris\Framework\Http\Cookies;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Implement psr request bridge behavior for the Http subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PsrRequestBridge
{
   /**
    * Create an instance from psr request.
    *
    * @param object $psrRequest
    * @return Request
    */
   public static function fromPsrRequest(object $psrRequest): Request
   {
      foreach (['getMethod', 'getUri', 'getHeaders', 'getBody'] as $method) {
         if (!method_exists($psrRequest, $method)) {
            throw new InvalidArgumentException(sprintf('PSR request object must implement %s().', $method));
         }
      }

      $method = (string) $psrRequest->getMethod();
      $uri = (string) $psrRequest->getUri();
      $path = parse_url($uri, PHP_URL_PATH);
      $headers = is_array($psrRequest->getHeaders()) ? $psrRequest->getHeaders() : [];
      $body = (string) $psrRequest->getBody();
      $queryParams = method_exists($psrRequest, 'getQueryParams') ? (array) $psrRequest->getQueryParams() : [];
      $cookieParams = method_exists($psrRequest, 'getCookieParams') ? (array) $psrRequest->getCookieParams() : [];
      $serverParams = method_exists($psrRequest, 'getServerParams') ? (array) $psrRequest->getServerParams() : [];
      $parsedBody = method_exists($psrRequest, 'getParsedBody') ? $psrRequest->getParsedBody() : null;
      $uploaded = method_exists($psrRequest, 'getUploadedFiles') ? (array) $psrRequest->getUploadedFiles() : [];

      return new Request(
         $method,
         is_string($path) && $path !== '' ? $path : '/',
         $headers,
         $queryParams,
         $body,
         Cookies::fromArray($cookieParams),
         self::normalizeUploadedFiles($uploaded),
         $parsedBody,
         $serverParams,
      );
   }

   /**
    * @param array<string, mixed> $uploaded
    * @return array<string, UploadedFile|array>
    */
   private static function normalizeUploadedFiles(array $uploaded): array
   {
      $normalized = [];
      foreach ($uploaded as $key => $value) {
         if (is_array($value)) {
            $normalized[(string) $key] = self::normalizeUploadedFiles($value);
            continue;
         }

         if (is_object($value)) {
            $normalized[(string) $key] = UploadedFile::fromPsrUploadedFile($value);
         }
      }

      return $normalized;
   }
}




