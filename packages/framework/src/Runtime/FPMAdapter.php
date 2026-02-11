<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;

/**
 * Purpose: bridge framework contracts with an external/runtime f p m adapter integration.
 * How: translates data and control flow between internal abstractions and external protocols.
 * Used in framework: selected by runtime or integration layers for environment-specific I/O.
 */
final class FPMAdapter implements WorkerAdapterInterface
{
   private bool $served = false;

   /**
    * Handle start.
    *
    * @return void
    */
   public function start(): void
   {
      // FPM doesn't need to start a loop; requests are handled by the webserver.
   }

   /**
    * Handle next request.
    *
    * @return ?RuntimeRequest
    */
   public function nextRequest(): ?RuntimeRequest
   {
      if ($this->served) {
         return null;
      }

      $this->served = true;
      $ctx = RequestContext::fromGlobals($_SERVER);
      $req = Request::fromGlobals($_SERVER, $_GET, null, null, $_COOKIE ?? [], $_FILES ?? []);

      return new RuntimeRequest($ctx, $req);
   }

   /**
    * Handle send.
    *
    * @param RuntimeRequest $request
    * @param Response $response
    * @return void
    */
   public function send(RuntimeRequest $request, Response $response): void
   {
      http_response_code($response->getStatus());
      foreach ($response->headers()->toMultiValueArray() as $name => $values) {
         foreach ($values as $v) {
            header(sprintf('%s: %s', $name, $v), false);
         }
      }
      foreach ($response->getCookies() as $cookie) {
         header('Set-Cookie: ' . $cookie->toHeaderValue(), false);
      }

      $response->emitBody(static function (string $chunk): void {
         echo $chunk;
      });
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      // No-op for FPM; process exits after request.
   }

   /**
    * Handle stop.
    *
    * @return void
    */
   public function stop(): void
   {
      // No-op.
   }
}



