<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Purpose: define the contract for opaque token store interface behavior in the Security subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete security services and resolved via dependency injection.
 */
interface OpaqueTokenStoreInterface
{
   /**
    * Handle find.
    *
    * @param string $token
    * @return ?StoredCredential
    */
   public function find(string $token): ?StoredCredential;
}



