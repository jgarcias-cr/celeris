<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Implement auth result behavior for the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class AuthResult
{
   /** @var array<string, string|array<int, string>> */
   private array $headers;

   /**
    * @param array<string, string|array<int, string>> $headers
    */
   private function __construct(
      private bool $authenticated,
      private ?Identity $identity = null,
      private ?string $strategy = null,
      private ?string $tokenId = null,
      private ?string $error = null,
      array $headers = [],
   ) {
      $this->headers = $headers;
   }

   /**
    * Handle authenticated.
    *
    * @param Identity $identity
    * @param string $strategy
    * @param ?string $tokenId
    * @return self
    */
   public static function authenticated(Identity $identity, string $strategy, ?string $tokenId = null): self
   {
      return new self(true, $identity, $strategy, $tokenId, null);
   }

   /**
    * @param array<string, string|array<int, string>> $headers
    */
   public static function rejected(string $error = 'Unauthorized', array $headers = []): self
   {
      return new self(false, null, null, null, $error, $headers);
   }

   /**
    * Determine whether is authenticated.
    *
    * @return bool
    */
   public function isAuthenticated(): bool
   {
      return $this->authenticated;
   }

   /**
    * Handle identity.
    *
    * @return ?Identity
    */
   public function identity(): ?Identity
   {
      return $this->identity;
   }

   /**
    * Handle strategy.
    *
    * @return ?string
    */
   public function strategy(): ?string
   {
      return $this->strategy;
   }

   /**
    * Convert to ken id.
    *
    * @return ?string
    */
   public function tokenId(): ?string
   {
      return $this->tokenId;
   }

   /**
    * Handle error.
    *
    * @return ?string
    */
   public function error(): ?string
   {
      return $this->error;
   }

   /**
    * @return array<string, string|array<int, string>>
    */
   public function headers(): array
   {
      return $this->headers;
   }
}



