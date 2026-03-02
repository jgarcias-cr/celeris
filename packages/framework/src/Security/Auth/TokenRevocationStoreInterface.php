<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Define the contract for token revocation store interface behavior in the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



