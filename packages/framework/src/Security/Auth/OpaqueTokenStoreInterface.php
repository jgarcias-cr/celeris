<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Auth;

/**
 * Define the contract for opaque token store interface behavior in the Security subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



