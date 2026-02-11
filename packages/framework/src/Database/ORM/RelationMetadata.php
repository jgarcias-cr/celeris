<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM;

/**
 * Purpose: implement relation metadata behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when relation metadata functionality is required.
 */
final class RelationMetadata
{
   /**
    * Create a new instance.
    *
    * @param string $property
    * @param string $targetEntity
    * @param string $localKey
    * @param string $targetKey
    * @return mixed
    */
   public function __construct(
      private string $property,
      private string $targetEntity,
      private string $localKey,
      private string $targetKey,
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
    * Handle target entity.
    *
    * @return string
    */
   public function targetEntity(): string
   {
      return $this->targetEntity;
   }

   /**
    * Handle local key.
    *
    * @return string
    */
   public function localKey(): string
   {
      return $this->localKey;
   }

   /**
    * Handle target key.
    *
    * @return string
    */
   public function targetKey(): string
   {
      return $this->targetKey;
   }
}



