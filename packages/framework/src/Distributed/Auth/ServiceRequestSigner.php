<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Auth;

use Celeris\Framework\Http\Request;

/**
 * Purpose: implement service request signer behavior for the Distributed subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by distributed components when service request signer functionality is required.
 */
final class ServiceRequestSigner
{
   /**
    * Create a new instance.
    *
    * @param string $serviceId
    * @param string $secret
    * @param ?\Closure $clock
    * @return mixed
    */
   public function __construct(
      private string $serviceId,
      private string $secret,
      private ?\Closure $clock = null,
   ) {
   }

   /**
    * Handle sign.
    *
    * @param Request $request
    * @return Request
    */
   public function sign(Request $request): Request
   {
      $issuedAt = (int) floor(($this->clock !== null ? ($this->clock)() : microtime(true)));
      $signature = self::signatureFor($request, $this->serviceId, $issuedAt, $this->secret);

      return $request->withHeaders([
         ...$request->getHeaders(),
         'x-service-id' => $this->serviceId,
         'x-service-ts' => (string) $issuedAt,
         'x-service-signature' => $signature,
      ]);
   }

   /**
    * Handle signature for.
    *
    * @param Request $request
    * @param string $serviceId
    * @param int $issuedAt
    * @param string $secret
    * @return string
    */
   public static function signatureFor(Request $request, string $serviceId, int $issuedAt, string $secret): string
   {
      $canonical = implode("\n", [
         strtoupper($request->getMethod()),
         $request->getPath(),
         (string) $issuedAt,
         hash('sha256', $request->getBody()),
         $serviceId,
      ]);

      return base64_encode(hash_hmac('sha256', $canonical, $secret, true));
   }
}



