<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\ORM\EntityManager;

/**
 * Purpose: abstract how Active Record resolves an entity manager per connection/operation.
 * How: defines a narrow contract returning an `EntityManager` for a given connection name.
 * Used in framework: injected into the Active Record manager to keep connection strategy explicit and testable.
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
