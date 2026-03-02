<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\ORM\EntityManager;

/**
 * Abstract how Active Record resolves an entity manager per connection/operation.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface EntityManagerResolverInterface
{
   /**
    * Resolve an entity manager for the requested connection.
    *
    * @param string $connection
    * @return EntityManager
    */
   public function resolve(string $connection = 'default'): EntityManager;
}
