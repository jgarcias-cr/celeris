<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\ORM\EntityManager;

/**
 * Purpose: reuse a prebuilt entity manager for Active Record operations.
 * How: always returns the same manager instance regardless of requested connection name.
 * Used in framework: useful for tests and advanced custom wiring where manager lifecycle is externally controlled.
 */
final class FixedEntityManagerResolver implements EntityManagerResolverInterface
{
   /**
    * Create a new resolver.
    *
    * @param EntityManager $entityManager
    * @return mixed
    */
   public function __construct(private EntityManager $entityManager)
   {
   }

   /**
    * Return the fixed entity manager instance.
    *
    * @param string $connection
    * @return EntityManager
    */
   public function resolve(string $connection = 'default'): EntityManager
   {
      return $this->entityManager;
   }
}
