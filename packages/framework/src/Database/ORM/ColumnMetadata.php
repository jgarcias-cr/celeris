<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Implement column metadata behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
    * @param IdGenerationStrategy $idStrategy
    * @param ?string $idSequence
    * @param bool $nullable
    * @param bool $readOnly
    * @return mixed
    */
   public function __construct(
      private string $property,
      private string $column,
      private bool $id,
      private bool $generated,
      private IdGenerationStrategy $idStrategy,
      private ?string $idSequence,
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
    * Handle id strategy.
    *
    * @return IdGenerationStrategy
    */
   public function idStrategy(): IdGenerationStrategy
   {
      return $this->idStrategy;
   }

   /**
    * Handle id sequence.
    *
    * @return ?string
    */
   public function idSequence(): ?string
   {
      return $this->idSequence;
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


