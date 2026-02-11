<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement length behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when length functionality is required.
 */
final class Length
{
   /**
    * Create a new instance.
    *
    * @param ?int $min
    * @param ?int $max
    * @return mixed
    */
   public function __construct(
      public ?int $min = null,
      public ?int $max = null,
   ) {
   }
}



