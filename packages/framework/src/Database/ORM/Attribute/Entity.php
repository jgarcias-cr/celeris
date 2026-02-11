<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Purpose: implement entity behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when entity functionality is required.
 */
final class Entity
{
   /**
    * Create a new instance.
    *
    * @param string $table
    * @param string $primaryKey
    * @return mixed
    */
   public function __construct(
      public string $table,
      public string $primaryKey = 'id',
   ) {
   }
}



