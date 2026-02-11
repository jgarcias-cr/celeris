<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Purpose: implement column behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when column functionality is required.
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



