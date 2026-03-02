<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\ORM\EntityManager;

/**
 * Reuse a prebuilt entity manager for Active Record operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
