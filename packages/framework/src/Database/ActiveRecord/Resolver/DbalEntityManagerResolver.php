<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Resolver;

use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\EntityManager;
use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Database\ORM\MetadataFactory;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;

/**
 * Build fresh `EntityManager` instances from DBAL connections for Active Record operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
