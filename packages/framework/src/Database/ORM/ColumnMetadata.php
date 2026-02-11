<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Purpose: implement column metadata behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when column metadata functionality is required.
 */
final class ColumnMetadata
{
   /**
    * Create a new instance.
    *
    * @param string $property
    * @param string $column
    * @param bool $id
    * @param bool $generated
    * @param bool $nullable
    * @param bool $readOnly
    * @return mixed
    */
   public function __construct(
      private string $property,
      private string $column,
      private bool $id,
      private bool $generated,
      private bool $nullable,
      private bool $readOnly,
   ) {
   }

   /**
    * Handle property.
    *
    * @return string
    */
   public function property(): string
   {
      return $this->property;
   }

   /**
    * Handle column.
    *
    * @return string
    */
   public function column(): string
   {
      return $this->column;
   }

   /**
    * Determine whether is id.
    *
    * @return bool
    */
   public function isId(): bool
   {
      return $this->id;
   }

   /**
    * Handle generated.
    *
    * @return bool
    */
   public function generated(): bool
   {
      return $this->generated;
   }

   /**
    * Handle nullable.
    *
    * @return bool
    */
   public function nullable(): bool
   {
      return $this->nullable;
   }

   /**
    * Handle read only.
    *
    * @return bool
    */
   public function readOnly(): bool
   {
      return $this->readOnly;
   }
}



