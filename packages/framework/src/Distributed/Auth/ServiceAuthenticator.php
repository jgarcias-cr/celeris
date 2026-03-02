<?php

declare(strict_types=1);

namespace Celeris\Framework\Distributed\Auth;

use Celeris\Framework\Http\Request;

/**
 * Implement service authenticator behavior for the Distributed subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ServiceAuthenticator
{
   /** @var array<string, string> */
   private array $sharedSecrets;

   /**
    * Create a new instance.
    *
    * @param array $sharedSecrets
    * @param int $maxClockSkewSeconds
    * @param ?\Closure $clock
    * @return mixed
    */
   public function __construct(
      array $sharedSecrets,
      private int $maxClockSkewSeconds = 120,
      private ?\Closure $clock = null,
   ) {
      $this->sharedSecrets = [];
      foreach ($sharedSecrets as $serviceId => $secret) {
         $id = trim((string) $serviceId);
         if ($id === '') {
            continue;
         }

         $this->sharedSecrets[$id] = (string) $secret;
      }
   }

   /**
    * Handle authenticate.
    *
    * @param Request $request
    * @return ServiceAuthResult
    */
   public function authenticate(Request $request): ServiceAuthResult
   {
      $serviceId = trim((string) ($request->getHeader('x-service-id') ?? ''));
      $issuedAtRaw = $request->getHeader('x-service-ts');
      $signature = trim((string) ($request->getHeader('x-service-signature') ?? ''));

      if ($serviceId === '' || !is_string($issuedAtRaw) || trim($issuedAtRaw) === '' || $signature === '') {
         return ServiceAuthResult::rejected('Missing service authentication headers.');
      }
      if (!isset($this->sharedSecrets[$serviceId])) {
         return ServiceAuthResult::rejected(sprintf('Unknown service "%s".', $serviceId));
      }
      if (!preg_match('/^-?[0-9]+$/', $issuedAtRaw)) {
         return ServiceAuthResult::rejected('Invalid service timestamp.');
      }

      $issuedAt = (int) $issuedAtRaw;
      $now = (int) floor($this->clock !== null ? ($this->clock)() : microtime(true));
      if (abs($issuedAt - $now) > $this->maxClockSkewSeconds) {
         return ServiceAuthResult::rejected('Service authentication token expired or clock-skewed.');
      }

      $expected = ServiceRequestSigner::signatureFor($request, $serviceId, $issuedAt, $this->sharedSecrets[$serviceId]);
      if (!hash_equals($expected, $signature)) {
         return ServiceAuthResult::rejected('Invalid service signature.');
      }

      return ServiceAuthResult::accepted(new ServicePrincipal($serviceId, $issuedAt, $signature));
   }
}



