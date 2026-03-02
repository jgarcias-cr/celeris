<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

use Celeris\Framework\Database\ActiveRecord\Exception\ModelNotFoundException;
use Celeris\Framework\Database\ActiveRecord\Exception\ValidationFailedException;
use Celeris\Framework\Database\ActiveRecord\Resolver\EntityManagerResolverInterface;
use Celeris\Framework\Database\ActiveRecord\Validation\ActiveRecordValidatorInterface;
use Celeris\Framework\Database\ActiveRecord\Validation\MetadataConstraintValidator;
use Celeris\Framework\Database\ORM\ColumnMetadata;
use Celeris\Framework\Database\ORM\EntityManager;
use Celeris\Framework\Database\ORM\EntityMetadata;
use Celeris\Framework\Database\ORM\LazyReference;
use Celeris\Framework\Database\ORM\MetadataFactory;
use Celeris\Framework\Database\Query\QueryBuilder;
use Celeris\Framework\Database\Sql\SqlDialectResolver;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Orchestrate CRUD, query, hydration, and validation for the Active Record compatibility layer.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ActiveRecordManager
{
   /**
    * Create a new manager.
    *
    * @param EntityManagerResolverInterface $entityManagerResolver
    * @param ?MetadataFactory $metadataFactory
    * @param ?ActiveRecordValidatorInterface $validator
    * @return mixed
    */
   public function __construct(
      private EntityManagerResolverInterface $entityManagerResolver,
      private ?MetadataFactory $metadataFactory = null,
      private ?ActiveRecordValidatorInterface $validator = null,
   ) {
      $this->metadataFactory ??= new MetadataFactory();
      $this->validator ??= new MetadataConstraintValidator();
   }

   /**
    * Build a query object for the requested model class.
    *
    * @template T of ActiveRecordModel
    * @param class-string<T> $modelClass
    * @return ActiveRecordQuery<T>
    */
   public function query(string $modelClass): ActiveRecordQuery
   {
      $this->ensureModelClass($modelClass);
      return new ActiveRecordQuery($this, $modelClass);
   }

   /**
    * Find one model by primary key.
    *
    * @template T of ActiveRecordModel
    * @param class-string<T> $modelClass
    * @param string|int $id
    * @return ?T
    */
   public function find(string $modelClass, string|int $id): ?ActiveRecordModel
   {
      $this->ensureModelClass($modelClass);
      $entity = $this->entityManagerFor($modelClass)->find($modelClass, $id);
      if ($entity === null) {
         return null;
      }

      if (!$entity instanceof ActiveRecordModel) {
         throw new ActiveRecordException(sprintf(
            'Entity "%s" must extend "%s" to be used with ActiveRecord manager.',
            $modelClass,
            ActiveRecordModel::class,
         ));
      }

      $entity->__arMarkPersisted(true);
      $entity->__arClearDirty();
      return $entity;
   }

   /**
    * Find one model by primary key or throw when missing.
    *
    * @template T of ActiveRecordModel
    * @param class-string<T> $modelClass
    * @param string|int $id
    * @return T
    * @throws ModelNotFoundException
    */
   public function findOrFail(string $modelClass, string|int $id): ActiveRecordModel
   {
      $model = $this->find($modelClass, $id);
      if (!$model instanceof ActiveRecordModel) {
         throw ModelNotFoundException::forId($modelClass, $id);
      }

      return $model;
   }

   /**
    * Persist a model instance.
    *
    * @param ActiveRecordModel $model
    * @return void
    * @throws ValidationFailedException
    */
   public function save(ActiveRecordModel $model): void
   {
      $metadata = $this->metadataFor($model::class);
      $validation = $this->validator->validate($model, $metadata);
      if (!$validation->isValid()) {
         throw new ValidationFailedException(
            sprintf('Validation failed for model "%s".', $model::class),
            $validation->errors(),
         );
      }

      $entityManager = $this->entityManagerFor($model::class);
      if ($model->__arExists()) {
         $entityManager->markDirty($model);
      } else {
         $entityManager->persist($model);
      }

      $entityManager->flush();
      $model->__arMarkPersisted(true);
      $model->__arClearDirty();
   }

   /**
    * Delete a model instance when it has a primary key value.
    *
    * @param ActiveRecordModel $model
    * @return void
    */
   public function delete(ActiveRecordModel $model): void
   {
      $metadata = $this->metadataFor($model::class);
      $id = $model->__arReadMappedValue($metadata->primaryProperty());
      if ($id === null || $id === '') {
         return;
      }

      $entityManager = $this->entityManagerFor($model::class);
      $entityManager->remove($model);
      $entityManager->flush();

      $model->__arMarkPersisted(false);
      $model->__arClearDirty();
   }

   /**
    * Reload a model from storage by copying fresh mapped state onto the same object.
    *
    * @param ActiveRecordModel $model
    * @return void
    */
   public function refresh(ActiveRecordModel $model): void
   {
      $metadata = $this->metadataFor($model::class);
      $id = $model->__arReadMappedValue($metadata->primaryProperty());
      if (!is_int($id) && !is_string($id)) {
         return;
      }

      $fresh = $this->find($model::class, $id);
      if (!$fresh instanceof ActiveRecordModel) {
         return;
      }

      $this->copyMappedState($fresh, $model, $metadata);
      $model->__arMarkPersisted(true);
      $model->__arClearDirty();
   }

   /**
    * Resolve a declared lazy relation through the underlying EntityManager.
    *
    * @param ActiveRecordModel $model
    * @param string $relationProperty
    * @return mixed
    */
   public function loadRelation(ActiveRecordModel $model, string $relationProperty): mixed
   {
      return $this->entityManagerFor($model::class)->loadRelation($model, $relationProperty);
   }

   /**
    * Execute a deterministic query and hydrate matching models.
    *
    * @template T of ActiveRecordModel
    * @param class-string<T> $modelClass
    * @param array<int, array{property: string, operator: string, value: mixed}> $conditions
    * @param array<int, array{property: string, direction: string}> $orders
    * @param ?int $limit
    * @param ?int $offset
    * @return array<int, T>
    */
   public function fetchByQuery(
      string $modelClass,
      array $conditions = [],
      array $orders = [],
      ?int $limit = null,
      ?int $offset = null,
   ): array {
      $this->ensureModelClass($modelClass);
      $metadata = $this->metadataFor($modelClass);
      $entityManager = $this->entityManagerFor($modelClass);

      $columns = array_map(
         static fn (ColumnMetadata $column): string => $column->column(),
         array_values($metadata->columns())
      );

      $builder = (new QueryBuilder(SqlDialectResolver::forConnection($entityManager->connection())))
         ->select($columns)
         ->from($metadata->table());

      foreach (array_values($conditions) as $index => $condition) {
         $property = trim((string) ($condition['property'] ?? ''));
         if ($property === '') {
            continue;
         }

         $operator = $this->normalizeOperator((string) ($condition['operator'] ?? '='));
         $column = $this->resolveColumn($metadata, $property);
         $paramName = 'w_' . $index;
         $expression = sprintf('%s %s :%s', $column, $operator, $paramName);
         $params = [$paramName => $condition['value'] ?? null];

         if ($index === 0) {
            $builder->where($expression, $params);
         } else {
            $builder->andWhere($expression, $params);
         }
      }

      foreach ($orders as $order) {
         $column = $this->resolveColumn($metadata, (string) ($order['property'] ?? ''));
         $direction = $this->normalizeDirection((string) ($order['direction'] ?? 'ASC'));
         $builder->orderBy(sprintf('%s %s', $column, $direction));
      }

      if ($limit !== null) {
         $builder->limit(max(0, $limit));
      }
      if ($offset !== null) {
         $builder->offset(max(0, $offset));
      }

      $query = $builder->build();
      $rows = $entityManager->connection()->fetchAll($query->sql(), $query->params());

      /** @var array<int, T> $models */
      $models = [];
      foreach ($rows as $row) {
         $model = $this->hydrateRow($modelClass, $metadata, $entityManager, $row);
         $models[] = $model;
      }

      return $models;
   }

   /**
    * Copy mapped properties/relations between model instances.
    *
    * @param ActiveRecordModel $source
    * @param ActiveRecordModel $target
    * @param EntityMetadata $metadata
    * @return void
    */
   private function copyMappedState(ActiveRecordModel $source, ActiveRecordModel $target, EntityMetadata $metadata): void
   {
      foreach ($metadata->columns() as $column) {
         $value = $this->readPropertyValue($source, $column->property());
         $this->setPropertyValue($target, $column->property(), $value);
      }

      foreach ($metadata->relations() as $relation) {
         $value = $this->readPropertyValue($source, $relation->property());
         $this->setPropertyValue($target, $relation->property(), $value);
      }
   }

   /**
    * Hydrate one model row from raw query data.
    *
    * @template T of ActiveRecordModel
    * @param class-string<T> $modelClass
    * @param EntityMetadata $metadata
    * @param EntityManager $entityManager
    * @param array<string, mixed> $row
    * @return T
    */
   private function hydrateRow(
      string $modelClass,
      EntityMetadata $metadata,
      EntityManager $entityManager,
      array $row,
   ): ActiveRecordModel {
      $reflection = new ReflectionClass($modelClass);
      $entity = $reflection->newInstanceWithoutConstructor();

      if (!$entity instanceof ActiveRecordModel) {
         throw new ActiveRecordException(sprintf('Class "%s" must extend ActiveRecordModel.', $modelClass));
      }

      foreach ($metadata->columns() as $column) {
         $rawValue = $row[$column->column()] ?? null;
         $value = $this->castForProperty($entity, $column->property(), $rawValue);
         $this->setPropertyValue($entity, $column->property(), $value);
      }

      foreach ($metadata->relations() as $relation) {
         $this->setPropertyValue(
            $entity,
            $relation->property(),
            new LazyReference(function () use ($entity, $relation, $entityManager): mixed {
               return $entityManager->loadRelation($entity, $relation->property());
            }),
         );
      }

      $entity->__arMarkPersisted(true);
      $entity->__arClearDirty();
      return $entity;
   }

   /**
    * Resolve metadata for a model class.
    *
    * @param string $modelClass
    * @return EntityMetadata
    */
   private function metadataFor(string $modelClass): EntityMetadata
   {
      return $this->metadataFactory->metadataFor($modelClass);
   }

   /**
    * Resolve the entity manager for a specific model class/connection.
    *
    * @param string $modelClass
    * @return EntityManager
    */
   private function entityManagerFor(string $modelClass): EntityManager
   {
      $this->ensureModelClass($modelClass);
      $connection = $this->resolveConnectionName($modelClass);
      return $this->entityManagerResolver->resolve($connection);
   }

   /**
    * Resolve a model class connection name with deterministic default fallback.
    *
    * @param string $modelClass
    * @return string
    */
   private function resolveConnectionName(string $modelClass): string
   {
      if (!is_subclass_of($modelClass, ActiveRecordModel::class)) {
         return 'default';
      }

      $connection = $modelClass::connectionName();
      if (!is_string($connection)) {
         return 'default';
      }

      $clean = trim($connection);
      return $clean !== '' ? $clean : 'default';
   }

   /**
    * Resolve model property-or-column input into a physical column name.
    *
    * @param EntityMetadata $metadata
    * @param string $propertyOrColumn
    * @return string
    */
   private function resolveColumn(EntityMetadata $metadata, string $propertyOrColumn): string
   {
      $clean = trim($propertyOrColumn);
      if ($clean === '') {
         throw new ActiveRecordException(sprintf('Invalid empty property/column for "%s".', $metadata->className()));
      }

      $mapped = $metadata->column($clean);
      if ($mapped instanceof ColumnMetadata) {
         return $mapped->column();
      }

      foreach ($metadata->columns() as $column) {
         if ($column->column() === $clean) {
            return $column->column();
         }
      }

      throw new ActiveRecordException(sprintf(
         'Unknown property/column "%s" for model "%s".',
         $clean,
         $metadata->className(),
      ));
   }

   /**
    * Normalize supported SQL operators to a safe allow-list.
    *
    * @param string $operator
    * @return string
    */
   private function normalizeOperator(string $operator): string
   {
      $clean = strtoupper(trim($operator));
      $allowed = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE'];
      if (!in_array($clean, $allowed, true)) {
         throw new ActiveRecordException(sprintf('Unsupported operator "%s".', $operator));
      }

      return $clean;
   }

   /**
    * Normalize order directions to `ASC`/`DESC`.
    *
    * @param string $direction
    * @return string
    */
   private function normalizeDirection(string $direction): string
   {
      $clean = strtoupper(trim($direction));
      return $clean === 'DESC' ? 'DESC' : 'ASC';
   }

   /**
    * Ensure a class is a valid ActiveRecord model type.
    *
    * @param string $modelClass
    * @return void
    */
   private function ensureModelClass(string $modelClass): void
   {
      if (!is_subclass_of($modelClass, ActiveRecordModel::class)) {
         throw new ActiveRecordException(sprintf(
            'Class "%s" must extend "%s" for ActiveRecord operations.',
            $modelClass,
            ActiveRecordModel::class,
         ));
      }
   }

   /**
    * Read a property value using reflection while handling uninitialized typed properties.
    *
    * @param object $entity
    * @param string $property
    * @return mixed
    */
   private function readPropertyValue(object $entity, string $property): mixed
   {
      $reflection = new ReflectionClass($entity);
      if (!$reflection->hasProperty($property)) {
         return null;
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);
      if (!$prop->isInitialized($entity)) {
         return null;
      }

      return $prop->getValue($entity);
   }

   /**
    * Set a property value using reflection while respecting readonly semantics.
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
         return;
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);
      if ($prop->isReadOnly() && $prop->isInitialized($entity)) {
         return;
      }

      $prop->setValue($entity, $this->castForProperty($entity, $property, $value));
   }

   /**
    * Cast scalar values according to builtin property type hints.
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
}
