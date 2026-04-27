<?php

declare(strict_types=1);

namespace Celeris\Framework\Security\Authorization;

use Celeris\Framework\Security\SecurityException;

final class AuthorizationException extends SecurityException
{
   public function __construct(string $message = 'This action is unauthorized.')
   {
      parent::__construct($message, 403);
   }
}
