<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Purpose: implement lazy relation behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when lazy relation functionality is required.
 */
final class LazyRelation
{
   /**
    * Create a new instance.
    *
    * @param string $targetEntity
    * @param string $localKey
    * @param string $targetKey
    * @return mixed
    */
   public function __construct(
      public string $targetEntity,
      public string $localKey,
      public string $targetKey = 'id',
   ) {
   }
}



