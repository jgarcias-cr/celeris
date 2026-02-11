<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Purpose: implement opaque token strategy behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when opaque token strategy functionality is required.
 */
final class OpaqueTokenStrategy implements AuthStrategyInterface
{
   /**
    * Create a new instance.
    *
    * @param OpaqueTokenStoreInterface $store
    * @return mixed
    */
   public function __construct(private OpaqueTokenStoreInterface $store)
   {
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'opaque_token';
   }

   /**
    * Determine whether supports.
    *
    * @param Request $request
    * @return bool
    */
   public function supports(Request $request): bool
   {
      $token = CredentialExtractor::bearerToken($request);
      if ($token === null) {
         return false;
      }

      return substr_count($token, '.') !== 2;
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
      $token = CredentialExtractor::bearerToken($request);
      if ($token === null) {
         return AuthResult::rejected('Missing bearer token.', ['www-authenticate' => 'Bearer']);
      }

      $credential = $this->store->find($token);
      if ($credential === null || $credential->isExpired()) {
         return AuthResult::rejected('Invalid or expired token.', ['www-authenticate' => 'Bearer error="invalid_token"']);
      }

      $tokenId = $credential->tokenId() ?? hash('sha256', $token);
      $identity = new Identity(
         $credential->subject(),
         $credential->roles(),
         $credential->permissions(),
         $credential->attributes(),
         microtime(true),
      );

      return AuthResult::authenticated($identity, $this->name(), $tokenId);
   }
}



