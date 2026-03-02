<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Csrf;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\SecurityException;

/**
 * Implement csrf protector behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class CsrfProtector
{
   /** @var array<int, string> */
   private array $protectedMethods;

   /**
    * @param array<int, string> $protectedMethods
    */
   public function __construct(
      private bool $enabled = true,
      array $protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'],
      private string $csrfCookie = 'csrf_token',
      private string $csrfHeader = 'x-csrf-token',
      private string $csrfField = '_csrf',
      private string $sessionCookie = 'session_id',
   ) {
      $this->protectedMethods = array_values(array_unique(array_map(
         static fn (mixed $method): string => strtoupper(trim((string) $method)),
         $protectedMethods
      )));
   }

   /**
    * Handle enforce.
    *
    * @param RequestContext $ctx
    * @param Request $request
    * @return void
    */
   public function enforce(RequestContext $ctx, Request $request): void
   {
      if (!$this->enabled) {
         return;
      }
      if (!in_array($request->getMethod(), $this->protectedMethods, true)) {
         return;
      }

      $hasSessionCookie = $request->getCookies()->get($this->sessionCookie) !== null;
      $authStrategy = (string) ($ctx->getAuth()['strategy'] ?? '');
      $isSessionAuthenticated = $authStrategy === 'cookie_session';
      if (!$hasSessionCookie && !$isSessionAuthenticated) {
         return;
      }

      $expected = trim((string) $request->getCookies()->get($this->csrfCookie, ''));
      $provided = trim((string) ($request->getHeader($this->csrfHeader) ?? $this->tokenFromRequestPayload($request)));
      if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
         throw new SecurityException('Invalid CSRF token.', 403);
      }
   }

   /**
    * Convert to ken from request payload.
    *
    * @param Request $request
    * @return ?string
    */
   private function tokenFromRequestPayload(Request $request): ?string
   {
      $parsed = $request->getParsedBody();
      if (is_array($parsed) && array_key_exists($this->csrfField, $parsed)) {
         return (string) $parsed[$this->csrfField];
      }

      $query = $request->getQueryParam($this->csrfField);
      return is_string($query) ? $query : null;
   }
}



