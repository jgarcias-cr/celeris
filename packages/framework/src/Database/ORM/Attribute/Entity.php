<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Implement entity behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



