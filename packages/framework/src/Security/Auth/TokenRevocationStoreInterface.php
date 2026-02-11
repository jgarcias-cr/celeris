<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: define the contract for token revocation store interface behavior in the Security subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete security services and resolved via dependency injection.
 */
interface TokenRevocationStoreInterface
{
   /**
    * Handle revoke.
    *
    * @param string $tokenId
    * @param ?float $expiresAt
    * @return void
    */
   public function revoke(string $tokenId, ?float $expiresAt = null): void;

   /**
    * Determine whether is revoked.
    *
    * @param string $tokenId
    * @param ?float $now
    * @return bool
    */
   public function isRevoked(string $tokenId, ?float $now = null): bool;
}



