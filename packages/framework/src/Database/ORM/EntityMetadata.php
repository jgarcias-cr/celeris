<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Implement entity metadata behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class EntityMetadata
{
   /** @var array<string, ColumnMetadata> */
   private array $columns;
   /** @var array<string, RelationMetadata> */
   private array $relations;

   /**
    * @param array<string, ColumnMetadata> $columns
    * @param array<string, RelationMetadata> $relations
    */
   public function __construct(
      private string $className,
      private string $table,
      private string $primaryProperty,
      private string $primaryColumn,
      array $columns,
      array $relations,
   ) {
      $this->columns = $columns;
      $this->relations = $relations;
   }

   /**
    * Handle class name.
    *
    * @return string
    */
   public function className(): string
   {
      return $this->className;
   }

   /**
    * Handle table.
    *
    * @return string
    */
   public function table(): string
   {
      return $this->table;
   }

   /**
    * Handle primary property.
    *
    * @return string
    */
   public function primaryProperty(): string
   {
      return $this->primaryProperty;
   }

   /**
    * Handle primary column.
    *
    * @return string
    */
   public function primaryColumn(): string
   {
      return $this->primaryColumn;
   }

   /**
    * @return array<string, ColumnMetadata>
    */
   public function columns(): array
   {
      return $this->columns;
   }

   /**
    * @return array<string, RelationMetadata>
    */
   public function relations(): array
   {
      return $this->relations;
   }

   /**
    * Handle column.
    *
    * @param string $property
    * @return ?ColumnMetadata
    */
   public function column(string $property): ?ColumnMetadata
   {
      return $this->columns[$property] ?? null;
   }

   /**
    * Handle relation.
    *
    * @param string $property
    * @return ?RelationMetadata
    */
   public function relation(string $property): ?RelationMetadata
   {
      return $this->relations[$property] ?? null;
   }
}



