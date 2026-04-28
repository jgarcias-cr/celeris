<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;

/**
 * Implement cookie session strategy behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class CookieSessionStrategy implements AuthStrategyInterface
{
   /**
    * Create a new instance.
    *
    * @param SessionStoreInterface $store
    * @param string $cookieName
    * @return mixed
    */
   public function __construct(
      private SessionStoreInterface $store,
      private string $cookieName = 'session_id',
      private ?CookieValueCodecInterface $cookieCodec = null,
      private bool $allowUnsignedCookieValue = false,
   ) {
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return 'cookie_session';
   }

   /**
    * Determine whether supports.
    *
    * @param Request $request
    * @return bool
    */
   public function supports(Request $request): bool
   {
      return $this->resolveSessionId($request) !== null;
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
      $sessionId = $this->resolveSessionId($request);
      if ($sessionId === null) {
         return AuthResult::rejected('Session cookie is missing.');
      }

      $credential = $this->store->find($sessionId);
      if ($credential === null || $credential->isExpired()) {
         return AuthResult::rejected('Invalid or expired session.');
      }

      $tokenId = $credential->tokenId() ?? $sessionId;
      $identity = new Identity(
         $credential->subject(),
         $credential->roles(),
         $credential->permissions(),
         $credential->attributes(),
         microtime(true),
      );

      return AuthResult::authenticated($identity, $this->name(), $tokenId);
   }

   /**
    * Resolve the session identifier from the incoming request.
    */
   private function resolveSessionId(Request $request): ?string
   {
      $raw = $request->getCookies()->get($this->cookieName);
      if (!is_string($raw) || trim($raw) === '') {
         return null;
      }

      if ($this->cookieCodec === null) {
         return $raw;
      }

      $decoded = $this->cookieCodec->decode($raw);
      if (is_string($decoded) && trim($decoded) !== '') {
         return $decoded;
      }

      if ($this->allowUnsignedCookieValue) {
         return $raw;
      }

      return null;
   }
}


