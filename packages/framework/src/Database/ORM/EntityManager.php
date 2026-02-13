<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

use Celeris\Framework\Database\Connection\PdoConnection;
use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Database\ORM\Event\EntityPersistedEvent;
use Celeris\Framework\Database\ORM\Event\EntityPersistingEvent;
use Celeris\Framework\Database\ORM\Event\EntityRemovedEvent;
use Celeris\Framework\Database\ORM\Event\EntityRemovingEvent;
use Celeris\Framework\Database\ORM\Event\EntityUpdatedEvent;
use Celeris\Framework\Database\ORM\Event\EntityUpdatingEvent;
use Celeris\Framework\Database\ORM\Event\PersistenceEventDispatcher;
use Celeris\Framework\Database\ORM\Event\PostFlushEvent;
use Celeris\Framework\Database\ORM\Event\PreFlushEvent;
use Celeris\Framework\Database\Query\QueryBuilder;
use Celeris\Framework\Database\Sql\SqlDialectResolver;
use Celeris\Framework\Domain\Event\DomainEventDispatcher;
use Celeris\Framework\Domain\Event\DomainEventInterface as FrameworkDomainEventInterface;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Purpose: implement entity manager behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when entity manager functionality is required.
 */
final class EntityManager
{
   private UnitOfWork $unitOfWork;
   /** @var array<string, array<string, object>> */
   private array $identityMap = [];

   /**
    * Create a new instance.
    *
    * @param ConnectionInterface $connection
    * @param ?MetadataFactory $metadataFactory
    * @param ?PersistenceEventDispatcher $persistenceEvents
    * @param ?DomainEventDispatcher $domainEvents
    * @param bool $strictHiddenQueries
    * @return mixed
    */
   public function __construct(
      private ConnectionInterface $connection,
      ?MetadataFactory $metadataFactory = null,
      ?PersistenceEventDispatcher $persistenceEvents = null,
      ?DomainEventDispatcher $domainEvents = null,
      private bool $strictHiddenQueries = true,
   ) {
      $this->metadataFactory = $metadataFactory ?? new MetadataFactory();
      $this->persistenceEvents = $persistenceEvents ?? new PersistenceEventDispatcher();
      $this->domainEvents = $domainEvents ?? new DomainEventDispatcher();
      $this->unitOfWork = new UnitOfWork();
   }

   private MetadataFactory $metadataFactory;
   private PersistenceEventDispatcher $persistenceEvents;
   private DomainEventDispatcher $domainEvents;

   /**
    * Handle persist.
    *
    * @param object $entity
    * @return void
    */
   public function persist(object $entity): void
   {
      $this->unitOfWork->registerNew($entity);
   }

   /**
    * Handle mark dirty.
    *
    * @param object $entity
    * @return void
    */
   public function markDirty(object $entity): void
   {
      $this->unitOfWork->registerDirty($entity);
   }

   /**
    * Handle remove.
    *
    * @param object $entity
    * @return void
    */
   public function remove(object $entity): void
   {
      $this->unitOfWork->registerRemoved($entity);
   }

   /**
    * Handle flush.
    *
    * @return void
    */
   public function flush(): void
   {
      if ($this->unitOfWork->isEmpty()) {
         return;
      }

      $marker = (object) ['flush' => true];
      $this->persistenceEvents->dispatch(new PreFlushEvent($marker));

      $newEntities = $this->unitOfWork->newEntities();
      $dirtyEntities = $this->unitOfWork->dirtyEntities();
      $removedEntities = $this->unitOfWork->removedEntities();

      $allChanged = [...$newEntities, ...$dirtyEntities, ...$removedEntities];

      $this->connection->transactional(function (ConnectionInterface $connection) use ($newEntities, $dirtyEntities, $removedEntities): void {
         foreach ($newEntities as $entity) {
            $this->insert($entity);
         }

         foreach ($dirtyEntities as $entity) {
            $this->update($entity);
         }

         foreach ($removedEntities as $entity) {
            $this->delete($entity);
         }
      });

      foreach ($allChanged as $entity) {
         $domainEvents = $this->pullDomainEvents($entity);
         if ($domainEvents !== []) {
            $this->domainEvents->dispatchMany($domainEvents);
         }
      }

      $this->unitOfWork->clear();
      $this->persistenceEvents->dispatch(new PostFlushEvent($marker));
   }

   /**
    * Handle find.
    *
    * @param string $className
    * @param string|int $id
    * @return ?object
    */
   public function find(string $className, string|int $id): ?object
   {
      $cached = $this->identityMap[$className][(string) $id] ?? null;
      if (is_object($cached)) {
         return $cached;
      }

      $metadata = $this->metadataFactory->metadataFor($className);
      $columns = array_map(
         static fn (ColumnMetadata $column): string => $column->column(),
         array_values($metadata->columns())
      );

      $query = $this->queryBuilder()
         ->select($columns)
         ->from($metadata->table())
         ->where(sprintf('%s = :id', $metadata->primaryColumn()), ['id' => $id])
         ->limit(1)
         ->build();

      $row = $this->connection->fetchOne($query->sql(), $query->params());
      if ($row === null) {
         return null;
      }

      $entity = $this->hydrate($metadata, $row);
      $identity = (string) $this->getPropertyValue($entity, $metadata->primaryProperty());
      $this->identityMap[$className][$identity] = $entity;
      return $entity;
   }

   /**
    * Handle load relation.
    *
    * @param object $entity
    * @param string $relationProperty
    * @return mixed
    */
   public function loadRelation(object $entity, string $relationProperty): mixed
   {
      $metadata = $this->metadataFactory->metadataFor($entity::class);
      $relation = $metadata->relation($relationProperty);
      if (!$relation instanceof RelationMetadata) {
         throw new OrmException(sprintf('Relation "%s" is not declared on "%s".', $relationProperty, $entity::class));
      }

      $propertyValue = $this->getPropertyValue($entity, $relationProperty);
      if ($propertyValue instanceof LazyReference) {
         return $propertyValue->load();
      }

      if ($propertyValue !== null) {
         return $propertyValue;
      }

      if ($this->strictHiddenQueries) {
         throw new OrmException(sprintf(
            'Lazy relation "%s" on "%s" must be explicitly initialized with LazyReference.',
            $relationProperty,
            $entity::class,
         ));
      }

      return $this->resolveRelation($entity, $relation);
   }

   /**
    * Handle connection.
    *
    * @return ConnectionInterface
    */
   public function connection(): ConnectionInterface
   {
      return $this->connection;
   }

   /**
    * Handle unit of work.
    *
    * @return UnitOfWork
    */
   public function unitOfWork(): UnitOfWork
   {
      return $this->unitOfWork;
   }

   /**
    * Handle persistence events.
    *
    * @return PersistenceEventDispatcher
    */
   public function persistenceEvents(): PersistenceEventDispatcher
   {
      return $this->persistenceEvents;
   }

   /**
    * Handle domain events.
    *
    * @return DomainEventDispatcher
    */
   public function domainEvents(): DomainEventDispatcher
   {
      return $this->domainEvents;
   }

   /**
    * Handle enable strict hidden queries.
    *
    * @param bool $enabled
    * @return void
    */
   public function enableStrictHiddenQueries(bool $enabled = true): void
   {
      $this->strictHiddenQueries = $enabled;
   }

   /**
    * Handle insert.
    *
    * @param object $entity
    * @return void
    */
   private function insert(object $entity): void
   {
      $metadata = $this->metadataFactory->metadataFor($entity::class);
      $this->persistenceEvents->dispatch(new EntityPersistingEvent($entity));

      $idMeta = $metadata->column($metadata->primaryProperty());
      $idStrategy = $idMeta instanceof ColumnMetadata
         ? $this->effectiveIdStrategy($metadata, $idMeta)
         : IdGenerationStrategy::None;

      if ($idMeta instanceof ColumnMetadata && $idMeta->generated()) {
         $currentId = $this->getPropertyValue($entity, $metadata->primaryProperty());
         if ($this->isEmptyIdentifier($currentId) && $idStrategy === IdGenerationStrategy::Sequence) {
            $sequenceName = $this->resolveSequenceName($metadata, $idMeta);
            $nextId = $this->nextSequenceValue($sequenceName);
            $this->setPropertyValue(
               $entity,
               $metadata->primaryProperty(),
               $this->castForProperty($entity, $metadata->primaryProperty(), $nextId),
            );
         }
      }

      $data = [];
      foreach ($metadata->columns() as $column) {
         $value = $this->getPropertyValue($entity, $column->property());
         if ($column->isId() && $column->generated() && $this->isEmptyIdentifier($value)) {
            continue;
         }

         if ($value === null && !$column->nullable()) {
            throw new OrmException(sprintf('Column "%s" cannot be null.', $column->column()));
         }

         $data[$column->column()] = $value;
      }

      $query = $this->queryBuilder()
         ->insert($metadata->table(), $data)
         ->build();

      $this->connection->execute($query->sql(), $query->params());

      if ($idMeta instanceof ColumnMetadata && $idMeta->generated()) {
         $currentId = $this->getPropertyValue($entity, $metadata->primaryProperty());
         if ($this->isEmptyIdentifier($currentId)) {
            if ($idStrategy === IdGenerationStrategy::Sequence) {
               throw new OrmException(sprintf(
                  'Generated id for "%s" is empty after sequence strategy; verify sequence mapping.',
                  $entity::class,
               ));
            }

            $id = $this->connection->lastInsertId();
            if ($id !== null && $id !== '') {
               $this->setPropertyValue($entity, $metadata->primaryProperty(), $this->castForProperty($entity, $metadata->primaryProperty(), $id));
            }
         }
      }

      $identity = (string) $this->getPropertyValue($entity, $metadata->primaryProperty());
      $this->identityMap[$entity::class][$identity] = $entity;

      $this->attachLazyReferences($entity, $metadata);
      $this->persistenceEvents->dispatch(new EntityPersistedEvent($entity));
   }

   /**
    * Handle update.
    *
    * @param object $entity
    * @return void
    */
   private function update(object $entity): void
   {
      $metadata = $this->metadataFactory->metadataFor($entity::class);
      $this->persistenceEvents->dispatch(new EntityUpdatingEvent($entity));

      $idValue = $this->getPropertyValue($entity, $metadata->primaryProperty());
      if ($idValue === null || $idValue === '') {
         throw new OrmException(sprintf('Cannot update "%s" without primary key.', $entity::class));
      }

      $data = [];
      foreach ($metadata->columns() as $column) {
         if ($column->isId() || $column->readOnly()) {
            continue;
         }

         $value = $this->getPropertyValue($entity, $column->property());
         if ($value === null && !$column->nullable()) {
            throw new OrmException(sprintf('Column "%s" cannot be null.', $column->column()));
         }

         $data[$column->column()] = $value;
      }

      if ($data !== []) {
         $query = $this->queryBuilder()
            ->update($metadata->table(), $data)
            ->where(sprintf('%s = :where_id', $metadata->primaryColumn()), ['where_id' => $idValue])
            ->build();

         $this->connection->execute($query->sql(), $query->params());
      }

      $this->attachLazyReferences($entity, $metadata);
      $this->persistenceEvents->dispatch(new EntityUpdatedEvent($entity));
   }

   /**
    * Handle delete.
    *
    * @param object $entity
    * @return void
    */
   private function delete(object $entity): void
   {
      $metadata = $this->metadataFactory->metadataFor($entity::class);
      $this->persistenceEvents->dispatch(new EntityRemovingEvent($entity));

      $idValue = $this->getPropertyValue($entity, $metadata->primaryProperty());
      if ($idValue === null || $idValue === '') {
         throw new OrmException(sprintf('Cannot delete "%s" without primary key.', $entity::class));
      }

      $query = $this->queryBuilder()
         ->delete($metadata->table())
         ->where(sprintf('%s = :where_id', $metadata->primaryColumn()), ['where_id' => $idValue])
         ->build();

      $this->connection->execute($query->sql(), $query->params());
      unset($this->identityMap[$entity::class][(string) $idValue]);
      $this->persistenceEvents->dispatch(new EntityRemovedEvent($entity));
   }

   /**
    * @param array<string, mixed> $row
    */
   private function hydrate(EntityMetadata $metadata, array $row): object
   {
      $reflection = new ReflectionClass($metadata->className());
      $entity = $reflection->newInstanceWithoutConstructor();

      foreach ($metadata->columns() as $column) {
         $rawValue = $row[$column->column()] ?? null;
         $value = $this->castForProperty($entity, $column->property(), $rawValue);
         $this->setPropertyValue($entity, $column->property(), $value);
      }

      $this->attachLazyReferences($entity, $metadata);
      return $entity;
   }

   /**
    * Handle attach lazy references.
    *
    * @param object $entity
    * @param EntityMetadata $metadata
    * @return void
    */
   private function attachLazyReferences(object $entity, EntityMetadata $metadata): void
   {
      foreach ($metadata->relations() as $relation) {
         $this->setPropertyValue($entity, $relation->property(), new LazyReference(function () use ($entity, $relation): mixed {
            return $this->resolveRelation($entity, $relation);
         }));
      }
   }

   /**
    * Handle resolve relation.
    *
    * @param object $entity
    * @param RelationMetadata $relation
    * @return mixed
    */
   private function resolveRelation(object $entity, RelationMetadata $relation): mixed
   {
      $localValue = $this->getPropertyValue($entity, $relation->localKey());
      if ($localValue === null || $localValue === '') {
         return null;
      }

      $targetMetadata = $this->metadataFactory->metadataFor($relation->targetEntity());
      if ($relation->targetKey() === $targetMetadata->primaryProperty() || $relation->targetKey() === $targetMetadata->primaryColumn()) {
         $id = is_int($localValue) || is_string($localValue) ? $localValue : (string) $localValue;
         return $this->find($relation->targetEntity(), $id);
      }

      $targetColumn = $relation->targetKey();
      $targetProperty = $relation->targetKey();
      $mapped = $targetMetadata->column($relation->targetKey());
      if ($mapped instanceof ColumnMetadata) {
         $targetColumn = $mapped->column();
         $targetProperty = $mapped->property();
      }

      $columns = array_map(
         static fn (ColumnMetadata $column): string => $column->column(),
         array_values($targetMetadata->columns())
      );

      $query = $this->queryBuilder()
         ->select($columns)
         ->from($targetMetadata->table())
         ->where(sprintf('%s = :value', $targetColumn), ['value' => $localValue])
         ->limit(1)
         ->build();

      $row = $this->connection->fetchOne($query->sql(), $query->params());
      if ($row === null) {
         return null;
      }

      $resolved = $this->hydrate($targetMetadata, $row);
      $identity = (string) $this->getPropertyValue($resolved, $targetMetadata->primaryProperty());
      $this->identityMap[$targetMetadata->className()][$identity] = $resolved;

      return $resolved;
   }

   /**
    * Get the property value.
    *
    * @param object $entity
    * @param string $property
    * @return mixed
    */
   private function getPropertyValue(object $entity, string $property): mixed
   {
      $reflection = new ReflectionClass($entity);
      if (!$reflection->hasProperty($property)) {
         throw new OrmException(sprintf('Property "%s" does not exist on "%s".', $property, $entity::class));
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);
      if (!$prop->isInitialized($entity)) {
         return null;
      }

      return $prop->getValue($entity);
   }

   /**
    * Set the property value.
    *
    * @param object $entity
    * @param string $property
    * @param mixed $value
    * @return void
    */
   private function setPropertyValue(object $entity, string $property, mixed $value): void
   {
      $reflection = new ReflectionClass($entity);
      if (!$reflection->hasProperty($property)) {
         throw new OrmException(sprintf('Property "%s" does not exist on "%s".', $property, $entity::class));
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);
      if ($prop->isReadOnly() && $prop->isInitialized($entity)) {
         return;
      }

      $prop->setValue($entity, $this->castForProperty($entity, $property, $value));
   }

   /**
    * Handle cast for property.
    *
    * @param object $entity
    * @param string $property
    * @param mixed $value
    * @return mixed
    */
   private function castForProperty(object $entity, string $property, mixed $value): mixed
   {
      $reflection = new ReflectionClass($entity);
      if (!$reflection->hasProperty($property)) {
         return $value;
      }

      $prop = $reflection->getProperty($property);
      $type = $prop->getType();
      if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
         return $value;
      }

      if ($value === null) {
         return null;
      }

      return match ($type->getName()) {
         'int' => (int) $value,
         'float' => (float) $value,
         'bool' => is_bool($value) ? $value : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
         'string' => (string) $value,
         'array' => is_array($value) ? $value : [$value],
         default => $value,
      };
   }

   private function isEmptyIdentifier(mixed $value): bool
   {
      return $value === null || $value === '';
   }

   private function effectiveIdStrategy(EntityMetadata $metadata, ColumnMetadata $idMeta): IdGenerationStrategy
   {
      if (!$idMeta->generated()) {
         return IdGenerationStrategy::None;
      }

      $declared = $idMeta->idStrategy();
      if ($declared !== IdGenerationStrategy::Auto) {
         return $declared;
      }

      $connectionDefault = $this->connectionDefaultIdStrategy();
      if ($connectionDefault !== IdGenerationStrategy::Auto) {
         return $connectionDefault;
      }

      $driver = $this->connectionDriver();
      if (
         $driver === DatabaseDriver::Oracle
         || $driver === DatabaseDriver::IBMDB2
         || $driver === DatabaseDriver::Firebird
      ) {
         if ($idMeta->idSequence() === null && $this->configuredSequenceName($metadata, $idMeta) === null) {
            throw new OrmException(sprintf(
               'Entity "%s" requires sequence-based id generation on driver "%s". Add #[Id(sequence: "...")] or connection option "id_sequence"/"id_sequence_pattern".',
               $metadata->className(),
               $driver->value,
            ));
         }

         return IdGenerationStrategy::Sequence;
      }

      return IdGenerationStrategy::Identity;
   }

   private function connectionDefaultIdStrategy(): IdGenerationStrategy
   {
      if (!$this->connection instanceof PdoConnection) {
         return IdGenerationStrategy::Auto;
      }

      $value = $this->connection->option('id_strategy');
      if (!is_scalar($value)) {
         return IdGenerationStrategy::Auto;
      }

      $clean = trim((string) $value);
      if ($clean === '') {
         return IdGenerationStrategy::Auto;
      }

      return IdGenerationStrategy::fromString($clean);
   }

   private function resolveSequenceName(EntityMetadata $metadata, ColumnMetadata $idMeta): string
   {
      $sequence = $idMeta->idSequence() ?? $this->configuredSequenceName($metadata, $idMeta);
      if (!is_string($sequence) || trim($sequence) === '') {
         throw new OrmException(sprintf(
            'Entity "%s" is configured with sequence id generation but no sequence name is available.',
            $metadata->className(),
         ));
      }

      return $this->normalizeSequenceName($sequence);
   }

   private function configuredSequenceName(EntityMetadata $metadata, ColumnMetadata $idMeta): ?string
   {
      if (!$this->connection instanceof PdoConnection) {
         return null;
      }

      $explicit = $this->connection->option('id_sequence');
      if (is_scalar($explicit)) {
         $clean = trim((string) $explicit);
         if ($clean !== '') {
            return $clean;
         }
      }

      $pattern = $this->connection->option('id_sequence_pattern');
      if (!is_scalar($pattern)) {
         return null;
      }

      $template = trim((string) $pattern);
      if ($template === '') {
         return null;
      }

      return strtr($template, [
         '{table}' => $metadata->table(),
         '{column}' => $idMeta->column(),
         '{property}' => $idMeta->property(),
      ]);
   }

   private function nextSequenceValue(string $sequence): mixed
   {
      foreach ($this->sequenceSqlCandidates($sequence) as [$sql, $params]) {
         try {
            $row = $this->connection->fetchOne($sql, $params);
            if (!is_array($row) || $row === []) {
               continue;
            }

            if (array_key_exists('celeris_id', $row)) {
               return $row['celeris_id'];
            }

            foreach ($row as $value) {
               return $value;
            }
         } catch (\Throwable) {
            // Try next vendor syntax.
         }
      }

      throw new OrmException(sprintf('Unable to fetch next sequence value for "%s".', $sequence));
   }

   /**
    * @return array<int, array{0: string, 1: array<string, mixed>}>
    */
   private function sequenceSqlCandidates(string $sequence): array
   {
      $driver = $this->connectionDriver();

      return match ($driver) {
         DatabaseDriver::Oracle => [
            [sprintf('SELECT %s.NEXTVAL AS celeris_id FROM DUAL', $sequence), []],
         ],
         DatabaseDriver::IBMDB2 => [
            [sprintf('SELECT NEXT VALUE FOR %s AS celeris_id FROM SYSIBM.SYSDUMMY1', $sequence), []],
            [sprintf('VALUES NEXT VALUE FOR %s', $sequence), []],
         ],
         DatabaseDriver::Firebird => [
            [sprintf('SELECT NEXT VALUE FOR %s AS celeris_id FROM RDB$DATABASE', $sequence), []],
            [sprintf('SELECT GEN_ID(%s, 1) AS celeris_id FROM RDB$DATABASE', $sequence), []],
         ],
         DatabaseDriver::PostgreSQL => [
            ['SELECT NEXTVAL(:sequence) AS celeris_id', ['sequence' => $sequence]],
         ],
         default => [
            ['SELECT NEXTVAL(:sequence) AS celeris_id', ['sequence' => $sequence]],
         ],
      };
   }

   private function connectionDriver(): ?DatabaseDriver
   {
      if ($this->connection instanceof PdoConnection) {
         return $this->connection->driver();
      }

      return null;
   }

   private function normalizeSequenceName(string $sequence): string
   {
      $clean = trim($sequence);
      if ($clean === '') {
         throw new OrmException('Sequence name cannot be empty.');
      }

      if (preg_match('/^[A-Za-z_][A-Za-z0-9_$#]*(?:\.[A-Za-z_][A-Za-z0-9_$#]*)*$/', $clean) !== 1) {
         throw new OrmException(sprintf('Invalid sequence identifier "%s".', $sequence));
      }

      return $clean;
   }

   /**
    * Handle query builder.
    *
    * @return QueryBuilder
    */
   private function queryBuilder(): QueryBuilder
   {
      return new QueryBuilder(SqlDialectResolver::forConnection($this->connection));
   }

   /**
    * @return array<int, FrameworkDomainEventInterface>
    */
   private function pullDomainEvents(object $entity): array
   {
      $events = [];

      if (method_exists($entity, 'pullDomainEvents')) {
         $events = $entity->pullDomainEvents();
      } elseif (method_exists($entity, 'releaseDomainEvents')) {
         $events = $entity->releaseDomainEvents();
      }

      if (!is_array($events)) {
         return [];
      }

      $filtered = [];
      foreach ($events as $event) {
         if ($event instanceof FrameworkDomainEventInterface) {
            $filtered[] = $event;
         }
      }

      return $filtered;
   }
}
