<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord;

use Celeris\Framework\Database\ActiveRecord\Exception\ModelNotFoundException;
use Celeris\Framework\Database\ORM\LazyReference;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Purpose: provide an optional Active Record facade over the existing Data Mapper ORM.
 * How: exposes static query/fetch methods, instance persistence methods, and magic property accessors.
 * Used in framework: extended by entities that want AR ergonomics while still using explicit metadata attributes.
 */
abstract class ActiveRecordModel
{
   private static ?ActiveRecordManager $manager = null;

   /** @var array<string, mixed> */
   private array $dynamicAttributes = [];
   private bool $arPersisted = false;
   private bool $arDirty = false;

   /** @var array<string, true> */
   private array $arDirtyProperties = [];

   /**
    * Bind the Active Record manager used by all model static/instance helpers.
    *
    * @param ActiveRecordManager $manager
    * @return void
    */
   public static function setManager(ActiveRecordManager $manager): void
   {
      self::$manager = $manager;
   }

   /**
    * Remove the currently bound manager.
    *
    * @return void
    */
   public static function clearManager(): void
   {
      self::$manager = null;
   }

   /**
    * Build a query object targeting the concrete model class.
    *
    * @return ActiveRecordQuery<static>
    */
   public static function query(): ActiveRecordQuery
   {
      return self::manager()->query(static::class);
   }

   /**
    * Find a model by primary key.
    *
    * @param string|int $id
    * @return ?static
    */
   public static function find(string|int $id): ?static
   {
      $model = self::manager()->find(static::class, $id);
      return $model instanceof static ? $model : null;
   }

   /**
    * Find a model by primary key or throw when missing.
    *
    * @param string|int $id
    * @return static
    * @throws ModelNotFoundException
    */
   public static function findOrFail(string|int $id): static
   {
      $model = self::manager()->findOrFail(static::class, $id);
      if (!$model instanceof static) {
         throw ModelNotFoundException::forId(static::class, $id);
      }

      return $model;
   }

   /**
    * Return all rows for this model class.
    *
    * @return array<int, static>
    */
   public static function all(): array
   {
      $rows = self::query()->get();
      /** @var array<int, static> $rows */
      return $rows;
   }

   /**
    * Start a query with an equality condition.
    *
    * @param string $property
    * @param mixed $value
    * @return ActiveRecordQuery<static>
    */
   public static function where(string $property, mixed $value): ActiveRecordQuery
   {
      return self::query()->where($property, $value);
   }

   /**
    * Create and persist a model from the given attributes.
    *
    * @param array<string, mixed> $attributes
    * @return static
    */
   public static function create(array $attributes): static
   {
      $model = new static();
      $model->fill($attributes);
      $model->save();
      return $model;
   }

   /**
    * Resolve the database connection name for this model.
    *
    * @return ?string
    */
   public static function connectionName(): ?string
   {
      return null;
   }

   /**
    * Persist this model through the bound Active Record manager.
    *
    * @return $this
    */
   public function save(): self
   {
      self::manager()->save($this);
      return $this;
   }

   /**
    * Delete this model through the bound Active Record manager.
    *
    * @return void
    */
   public function delete(): void
   {
      self::manager()->delete($this);
   }

   /**
    * Reload this model from storage using its primary key.
    *
    * @return $this
    */
   public function refresh(): self
   {
      self::manager()->refresh($this);
      return $this;
   }

   /**
    * Resolve a declared lazy relation explicitly.
    *
    * @param string $relationProperty
    * @return mixed
    */
   public function load(string $relationProperty): mixed
   {
      return self::manager()->loadRelation($this, $relationProperty);
   }

   /**
    * Fill model properties/dynamic attributes from key-value input.
    *
    * @param array<string, mixed> $attributes
    * @return $this
    */
   public function fill(array $attributes): self
   {
      foreach ($attributes as $property => $value) {
         $this->__set((string) $property, $value);
      }

      return $this;
   }

   /**
    * Export mapped and dynamic attributes to an array for transport/logging.
    *
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      $reflection = new ReflectionClass($this);
      $payload = [];

      foreach ($reflection->getProperties() as $property) {
         if ($property->isStatic()) {
            continue;
         }

         $name = $property->getName();
         if (str_starts_with($name, 'ar') || $name === 'dynamicAttributes') {
            continue;
         }

         $payload[$name] = $this->__arReadMappedValue($name);
      }

      return [...$payload, ...$this->dynamicAttributes];
   }

   /**
    * Return whether this model has been loaded from or persisted to storage.
    *
    * @return bool
    */
   public function exists(): bool
   {
      return $this->arPersisted;
   }

   /**
    * Return whether this model has pending in-memory mutations.
    *
    * @return bool
    */
   public function isDirty(): bool
   {
      return $this->arDirty;
   }

   /**
    * Return all dirty property names.
    *
    * @return array<int, string>
    */
   public function dirtyProperties(): array
   {
      return array_keys($this->arDirtyProperties);
   }

   /**
    * Override to define optional validation callbacks.
    *
    * Field rule shape: `propertyName => callable(mixed $value, self $model): bool|string|array|null`.
    * Model rule shape: `callable(self $model): bool|string|array|null`.
    *
    * @return array<int|string, callable>
    */
   public function validationRules(): array
   {
      return [];
   }

   /**
    * Read a mapped property or dynamic attribute used by AR internals.
    *
    * @param string $property
    * @return mixed
    */
   public function __arReadMappedValue(string $property): mixed
   {
      if ($this->hasRealProperty($property)) {
         return $this->readRealProperty($property);
      }

      return $this->dynamicAttributes[$property] ?? null;
   }

   /**
    * Write a mapped property or dynamic attribute used by AR internals.
    *
    * @param string $property
    * @param mixed $value
    * @param bool $markDirty
    * @return void
    */
   public function __arWriteMappedValue(string $property, mixed $value, bool $markDirty = true): void
   {
      if ($this->hasRealProperty($property)) {
         $this->writeRealProperty($property, $value, $markDirty);
         return;
      }

      $this->dynamicAttributes[$property] = $value;
      if ($markDirty) {
         $this->__arMarkDirty($property);
      }
   }

   /**
    * Mark persistence state after hydration/save/delete events.
    *
    * @param bool $persisted
    * @return void
    */
   public function __arMarkPersisted(bool $persisted = true): void
   {
      $this->arPersisted = $persisted;
   }

   /**
    * Return internal persistence state for manager internals.
    *
    * @return bool
    */
   public function __arExists(): bool
   {
      return $this->arPersisted;
   }

   /**
    * Mark one property as dirty.
    *
    * @param string $property
    * @return void
    */
   public function __arMarkDirty(string $property): void
   {
      $clean = trim($property);
      if ($clean === '') {
         return;
      }

      $this->arDirty = true;
      $this->arDirtyProperties[$clean] = true;
   }

   /**
    * Reset dirty flags after successful persistence/hydration.
    *
    * @return void
    */
   public function __arClearDirty(): void
   {
      $this->arDirty = false;
      $this->arDirtyProperties = [];
   }

   /**
    * Magic getter that resolves real mapped properties and lazy relation wrappers.
    *
    * @param string $name
    * @return mixed
    */
   public function __get(string $name): mixed
   {
      if ($this->hasRealProperty($name)) {
         $value = $this->readRealProperty($name);
         if ($value instanceof LazyReference) {
            return $value->load();
         }

         return $value;
      }

      return $this->dynamicAttributes[$name] ?? null;
   }

   /**
    * Magic setter that mutates mapped properties with type casting and dirty tracking.
    *
    * @param string $name
    * @param mixed $value
    * @return void
    */
   public function __set(string $name, mixed $value): void
   {
      $this->__arWriteMappedValue($name, $value, true);
   }

   /**
    * Determine if a mapped or dynamic attribute is set.
    *
    * @param string $name
    * @return bool
    */
   public function __isset(string $name): bool
   {
      $value = $this->__arReadMappedValue($name);
      return $value !== null;
   }

   /**
    * Remove a dynamic attribute or clear a nullable mapped property.
    *
    * @param string $name
    * @return void
    */
   public function __unset(string $name): void
   {
      if ($this->hasRealProperty($name)) {
         $this->writeRealProperty($name, null, true);
         return;
      }

      unset($this->dynamicAttributes[$name]);
      $this->__arMarkDirty($name);
   }

   /**
    * Resolve the globally bound manager or throw when AR is not bootstrapped.
    *
    * @return ActiveRecordManager
    */
   protected static function manager(): ActiveRecordManager
   {
      if (!self::$manager instanceof ActiveRecordManager) {
         throw new ActiveRecordException(
            sprintf(
               'ActiveRecord manager is not configured for "%s". Bind one via %s::setManager().',
               static::class,
               self::class,
            )
         );
      }

      return self::$manager;
   }

   /**
    * Determine whether the concrete model declares the given property.
    *
    * @param string $property
    * @return bool
    */
   private function hasRealProperty(string $property): bool
   {
      $reflection = new ReflectionClass($this);
      return $reflection->hasProperty($property);
   }

   /**
    * Read a declared model property with visibility bypass.
    *
    * @param string $property
    * @return mixed
    */
   private function readRealProperty(string $property): mixed
   {
      $reflection = new ReflectionClass($this);
      if (!$reflection->hasProperty($property)) {
         return null;
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);
      if (!$prop->isInitialized($this)) {
         return null;
      }

      return $prop->getValue($this);
   }

   /**
    * Write a declared model property with scalar casting and dirty tracking.
    *
    * @param string $property
    * @param mixed $value
    * @param bool $markDirty
    * @return void
    */
   private function writeRealProperty(string $property, mixed $value, bool $markDirty): void
   {
      $reflection = new ReflectionClass($this);
      if (!$reflection->hasProperty($property)) {
         $this->dynamicAttributes[$property] = $value;
         if ($markDirty) {
            $this->__arMarkDirty($property);
         }
         return;
      }

      $prop = $reflection->getProperty($property);
      $prop->setAccessible(true);

      if ($prop->isReadOnly() && $prop->isInitialized($this)) {
         return;
      }

      $prop->setValue($this, $this->castForProperty($prop->getType(), $value));
      if ($markDirty) {
         $this->__arMarkDirty($property);
      }
   }

   /**
    * Cast scalar values to match builtin typed properties.
    *
    * @param ?\ReflectionType $type
    * @param mixed $value
    * @return mixed
    */
   private function castForProperty(?\ReflectionType $type, mixed $value): mixed
   {
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
