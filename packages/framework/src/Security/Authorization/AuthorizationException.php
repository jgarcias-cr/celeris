<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Authorization;

use Celeris\Framework\Security\SecurityException;

/**
 * Signal that the current action failed an authorization check.
 */
final class AuthorizationException extends SecurityException
{
   /**
    * Create an authorization exception with a friendly default message.
    */
   public function __construct(string $message = 'This action is unauthorized.')
   {
      parent::__construct($message, 403);
   }
}
