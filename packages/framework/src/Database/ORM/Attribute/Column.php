<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Implement column behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Column
{
   /**
    * Create a new instance.
    *
    * @param ?string $name
    * @param bool $nullable
    * @param bool $readOnly
    * @return mixed
    */
   public function __construct(
      public ?string $name = null,
      public bool $nullable = false,
      public bool $readOnly = false,
   ) {
   }
}



