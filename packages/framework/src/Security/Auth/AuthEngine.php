<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\SecurityException;

/**
 * Purpose: orchestrate auth engine workflows within Security.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when auth engine functionality is required.
 */
final class AuthEngine
{
   /** @var array<int, AuthStrategyInterface> */
   private array $strategies;

   /**
    * @param array<int, AuthStrategyInterface> $strategies
    */
   public function __construct(
      array $strategies = [],
      private ?TokenRevocationStoreInterface $revocationStore = null,
   ) {
      $this->strategies = $strategies;
   }

   /**
    * Handle authenticate.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return RequestContext
    */
   public function authenticate(RequestContext $ctx, Request $request): RequestContext
   {
      foreach ($this->strategies as $strategy) {
         if (!$strategy->supports($request)) {
            continue;
         }

         $result = $strategy->authenticate($ctx, $request);
         if (!$result->isAuthenticated() || $result->identity() === null) {
            throw new SecurityException($result->error() ?? 'Unauthorized', 401, $result->headers());
         }

         $tokenId = $result->tokenId();
         if ($tokenId !== null && $this->revocationStore?->isRevoked($tokenId) === true) {
            throw new SecurityException(
               'Token has been revoked.',
               401,
               ['www-authenticate' => 'Bearer error="invalid_token"']
            );
         }

         $auth = $result->identity()->toArray();
         $auth['strategy'] = $result->strategy();
         $auth['token_id'] = $tokenId;

         return $ctx
            ->withAuth($auth)
            ->withAttribute('auth.identity', $result->identity())
            ->withAttribute('auth.strategy', $result->strategy())
            ->withAttribute('auth.token_id', $tokenId);
      }

      return $ctx
         ->withAuth(null)
         ->withoutAttribute('auth.identity')
         ->withoutAttribute('auth.strategy')
         ->withoutAttribute('auth.token_id');
   }

   /**
    * Handle revoke token.
    *
    * @param string $tokenId
    * @param ?float $expiresAt
    * @return void
    */
   public function revokeToken(string $tokenId, ?float $expiresAt = null): void
   {
      $this->revocationStore?->revoke($tokenId, $expiresAt);
   }

   /**
    * @return array<int, AuthStrategyInterface>
    */
   public function strategies(): array
   {
      return $this->strategies;
   }

   /**
    * Handle revocation store.
    *
    * @return ?TokenRevocationStoreInterface
    */
   public function revocationStore(): ?TokenRevocationStoreInterface
   {
      return $this->revocationStore;
   }
}



