<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Implement api token strategy behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ApiTokenStrategy implements AuthStrategyInterface
{
   /**
    * Create a new instance.
    *
    * @param ApiTokenStoreInterface $store
    * @param string $headerName
    * @param string $queryParam
    * @return mixed
    */
   public function __construct(
      private ApiTokenStoreInterface $store,
      private string $headerName = 'x-api-key',
      private string $queryParam = 'api_key',
   ) {
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'api_token';
   }

   /**
    * Determine whether supports.
    *
    * @param Request $request
    * @return bool
    */
   public function supports(Request $request): bool
   {
      return CredentialExtractor::apiToken($request, $this->headerName, $this->queryParam) !== null;
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
      $token = CredentialExtractor::apiToken($request, $this->headerName, $this->queryParam);
      if ($token === null) {
         return AuthResult::rejected('API token is missing.');
      }

      $credential = $this->store->find($token);
      if ($credential === null || $credential->isExpired()) {
         return AuthResult::rejected('Invalid or expired API token.');
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



