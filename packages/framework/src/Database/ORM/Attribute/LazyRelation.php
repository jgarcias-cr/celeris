<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Implement lazy relation behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



