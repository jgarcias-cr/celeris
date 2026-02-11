<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

use Celeris\Framework\Database\DatabaseException;
use Celeris\Framework\Database\ORM\Attribute\Column;
use Celeris\Framework\Database\ORM\Attribute\Entity;
use Celeris\Framework\Database\ORM\Attribute\Id;
use Celeris\Framework\Database\ORM\Attribute\LazyRelation;
use ReflectionClass;

/**
 * Purpose: build configured metadata factory instances for runtime wiring.
 * How: encapsulates construction rules so callers avoid scattered wiring logic.
 * Used in framework: invoked by database components when metadata factory functionality is required.
 */
final class MetadataFactory
{
   /** @var array<string, EntityMetadata> */
   private array $cache = [];

   /**
    * Handle metadata for.
    *
    * @param string $className
    * @return EntityMetadata
    */
   public function metadataFor(string $className): EntityMetadata
   {
      if (isset($this->cache[$className])) {
         return $this->cache[$className];
      }
      if (!class_exists($className)) {
         throw new DatabaseException(sprintf('Entity class "%s" does not exist.', $className));
      }

      $reflection = new ReflectionClass($className);
      $entityAttributes = $reflection->getAttributes(Entity::class);
      if ($entityAttributes === []) {
         throw new DatabaseException(sprintf('Class "%s" is not marked with #[Entity].', $className));
      }

      /** @var Entity $entity */
      $entity = $entityAttributes[0]->newInstance();

      $columns = [];
      $relations = [];
      $primaryProperty = null;
      $primaryColumn = null;

      foreach ($reflection->getProperties() as $property) {
         $name = $property->getName();

         $relationAttributes = $property->getAttributes(LazyRelation::class);
         if ($relationAttributes !== []) {
            /** @var LazyRelation $relation */
            $relation = $relationAttributes[0]->newInstance();
            $relations[$name] = new RelationMetadata(
               $name,
               $relation->targetEntity,
               $relation->localKey,
               $relation->targetKey,
            );
            continue;
         }

         $columnAttributes = $property->getAttributes(Column::class);
         $idAttributes = $property->getAttributes(Id::class);

         if ($columnAttributes === [] && $idAttributes === []) {
            continue;
         }

         $column = $columnAttributes !== []
            ? $columnAttributes[0]->newInstance()
            : new Column($name);

         if (!$column instanceof Column) {
            throw new DatabaseException(sprintf('Invalid column metadata on "%s::%s".', $className, $name));
         }

         $isId = $idAttributes !== [] || $name === $entity->primaryKey;
         $generated = true;
         if ($idAttributes !== []) {
            $id = $idAttributes[0]->newInstance();
            if ($id instanceof Id) {
               $generated = $id->generated;
            }
         }

         $columnName = $column->name ?? $name;
         $columns[$name] = new ColumnMetadata(
            $name,
            $columnName,
            $isId,
            $generated,
            $column->nullable,
            $column->readOnly,
         );

         if ($isId && $primaryProperty === null) {
            $primaryProperty = $name;
            $primaryColumn = $columnName;
         }
      }

      if ($primaryProperty === null || $primaryColumn === null) {
         throw new DatabaseException(sprintf('Entity "%s" must define an id column.', $className));
      }

      $metadata = new EntityMetadata(
         $className,
         $entity->table,
         $primaryProperty,
         $primaryColumn,
         $columns,
         $relations,
      );

      $this->cache[$className] = $metadata;
      return $metadata;
   }
}



