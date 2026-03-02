<?php

declare(strict_types=1);

namespace Celeris\Framework\Http\Psr;

use Celeris\Framework\Http\Response;
use InvalidArgumentException;

/**
 * Implement psr response bridge behavior for the Http subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PsrResponseBridge
{
   /**
    * @param callable(int): object $responseFactory
    * @param callable(string): object $streamFactory
    */
   public static function toPsrResponse(Response $response, callable $responseFactory, callable $streamFactory): object
   {
      $psrResponse = $responseFactory($response->getStatus());
      if (!is_object($psrResponse)) {
         throw new InvalidArgumentException('responseFactory must return a PSR response object.');
      }

      foreach ($response->headers()->toMultiValueArray() as $name => $values) {
         foreach ($values as $value) {
            if (method_exists($psrResponse, 'withAddedHeader')) {
               $psrResponse = $psrResponse->withAddedHeader($name, $value);
            } elseif (method_exists($psrResponse, 'withHeader')) {
               $psrResponse = $psrResponse->withHeader($name, $value);
            }
         }
      }

      foreach ($response->getCookies() as $cookie) {
         if (method_exists($psrResponse, 'withAddedHeader')) {
            $psrResponse = $psrResponse->withAddedHeader('set-cookie', $cookie->toHeaderValue());
         }
      }

      if (method_exists($psrResponse, 'withBody')) {
         $stream = $streamFactory($response->getBody());
         $psrResponse = $psrResponse->withBody($stream);
      }

      return $psrResponse;
   }
}



