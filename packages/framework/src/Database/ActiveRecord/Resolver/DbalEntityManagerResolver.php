<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\EntityManager;
use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Database\ORM\MetadataFactory;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;

/**
 * Purpose: build fresh `EntityManager` instances from DBAL connections for Active Record operations.
 * How: resolves a concrete connection by name and instantiates a new entity manager each time.
 * Used in framework: default resolver for worker-safe Active Record execution without cross-request identity map leakage.
 */
final class DbalEntityManagerResolver implements EntityManagerResolverInterface
{
   /**
    * Create a new resolver.
    *
    * @param DBAL $dbal
    * @param ?MetadataFactory $metadataFactory
    * @param ?PersistenceEventDispatcher $persistenceEvents
    * @param ?DomainEventDispatcher $domainEvents
    * @param bool $strictHiddenQueries
    * @return mixed
    */
   public function __construct(
      private DBAL $dbal,
      private ?MetadataFactory $metadataFactory = null,
      private ?PersistenceEventDispatcher $persistenceEvents = null,
      private ?DomainEventDispatcher $domainEvents = null,
      private bool $strictHiddenQueries = true,
   ) {
      $this->metadataFactory ??= new MetadataFactory();
      $this->persistenceEvents ??= new PersistenceEventDispatcher();
      $this->domainEvents ??= new DomainEventDispatcher();
   }

   /**
    * Resolve a fresh entity manager for the given connection name.
    *
    * @param string $connection
    * @return EntityManager
    */
   public function resolve(string $connection = 'default'): EntityManager
   {
      return new EntityManager(
         $this->dbal->connection($connection),
         $this->metadataFactory,
         $this->persistenceEvents,
         $this->domainEvents,
         $this->strictHiddenQueries,
      );
   }
}
