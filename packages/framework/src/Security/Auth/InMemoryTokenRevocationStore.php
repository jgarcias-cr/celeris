<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: implement in memory token revocation store behavior for the Security subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by security components when in memory token revocation store functionality is required.
 */
final class InMemoryTokenRevocationStore implements TokenRevocationStoreInterface
{
   /** @var array<string, ?float> */
   private array $revoked = [];

   /**
    * Handle revoke.
    *
    * @param string $tokenId
    * @param ?float $expiresAt
    * @return void
    */
   public function revoke(string $tokenId, ?float $expiresAt = null): void
   {
      $clean = trim($tokenId);
      if ($clean === '') {
         return;
      }

      $this->revoked[$clean] = $expiresAt;
   }

   /**
    * Determine whether is revoked.
    *
    * @param string $tokenId
    * @param ?float $now
    * @return bool
    */
   public function isRevoked(string $tokenId, ?float $now = null): bool
   {
      $clean = trim($tokenId);
      if ($clean === '') {
         return false;
      }
      if (!array_key_exists($clean, $this->revoked)) {
         return false;
      }

      $expiry = $this->revoked[$clean];
      if ($expiry === null) {
         return true;
      }

      $reference = $now ?? microtime(true);
      if ($reference >= $expiry) {
         unset($this->revoked[$clean]);
         return false;
      }

      return true;
   }
}



