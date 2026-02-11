<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Purpose: represent a domain-specific failure for Container operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by container components and surfaced through kernel error handling.
 */
final class NotFoundException extends ContainerException
{
   /**
    * Create a new instance.
    *
    * @param string $id
    * @return mixed
    */
   public function __construct(string $id)
   {
      parent::__construct(sprintf('Service "%s" is not registered.', $id));
   }
}




