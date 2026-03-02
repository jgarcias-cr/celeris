<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

/**
 * Represent a domain-specific failure for Container operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class RequestScopeRequiredException extends ContainerException
{
   /**
    * Create a new instance.
    *
    * @param string $id
    * @return mixed
    */
   public function __construct(string $id)
   {
      parent::__construct(sprintf('Service "%s" requires a request-scoped container.', $id));
   }
}




