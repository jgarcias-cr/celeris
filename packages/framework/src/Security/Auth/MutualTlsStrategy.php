<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Implement mutual tls strategy behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class MutualTlsStrategy implements AuthStrategyInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'mtls';
   }

   /**
    * Determine whether supports.
    *
    * @param Request $request
    * @return bool
    */
   public function supports(Request $request): bool
   {
      $verify = strtoupper(trim((string) ($request->getServerParams()['SSL_CLIENT_VERIFY'] ?? '')));
      return $verify !== '';
   }

   /**
    * Handle authenticate.
    *
    * @param RequestContext $context
    * @param Request $request
    * @return AuthResult
    */
   public function authenticate(RequestContext $context, Request $request): AuthResult
   {
      $server = $request->getServerParams();
      $verify = strtoupper(trim((string) ($server['SSL_CLIENT_VERIFY'] ?? '')));
      if ($verify !== 'SUCCESS') {
         return AuthResult::rejected('mTLS client certificate verification failed.');
      }

      $subject = trim((string) ($server['SSL_CLIENT_S_DN_CN'] ?? $server['SSL_CLIENT_S_DN'] ?? ''));
      if ($subject === '') {
         $subject = trim((string) ($server['SSL_CLIENT_M_SERIAL'] ?? ''));
      }
      if ($subject === '') {
         return AuthResult::rejected('mTLS certificate subject is missing.');
      }

      $cert = trim((string) ($server['SSL_CLIENT_CERT'] ?? ''));
      $tokenId = $cert !== '' ? hash('sha256', $cert) : hash('sha256', $subject);
      $attributes = [
         'dn' => $server['SSL_CLIENT_S_DN'] ?? null,
         'issuer' => $server['SSL_CLIENT_I_DN'] ?? null,
         'serial' => $server['SSL_CLIENT_M_SERIAL'] ?? null,
      ];

      $identity = new Identity($subject, ['mtls'], ['transport:mtls'], $attributes, microtime(true));
      return AuthResult::authenticated($identity, $this->name(), $tokenId);
   }
}



