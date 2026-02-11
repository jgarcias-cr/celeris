<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement range behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when range functionality is required.
 */
final class Range
{
   /**
    * Create a new instance.
    *
    * @param ?float $min
    * @param ?float $max
    * @return mixed
    */
   public function __construct(
      public ?float $min = null,
      public ?float $max = null,
   ) {
   }
}



