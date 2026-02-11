<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

use Celeris\Framework\Database\Connection\ConnectionInterface;

/**
 * Purpose: orchestrate orm engine workflows within Database.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when orm engine functionality is required.
 */
final class OrmEngine
{
   private EntityManager $entityManager;

   /**
    * Create a new instance.
    *
    * @param ConnectionInterface $connection
    * @param ?MetadataFactory $metadataFactory
    * @param ?EntityManager $entityManager
    * @return mixed
    */
   public function __construct(
      ConnectionInterface $connection,
      ?MetadataFactory $metadataFactory = null,
      ?EntityManager $entityManager = null,
   ) {
      $this->entityManager = $entityManager ?? new EntityManager(
         $connection,
         $metadataFactory,
      );
   }

   /**
    * Handle entity manager.
    *
    * @return EntityManager
    */
   public function entityManager(): EntityManager
   {
      return $this->entityManager;
   }

   /**
    * Handle flush.
    *
    * @return void
    */
   public function flush(): void
   {
      $this->entityManager->flush();
   }
}



