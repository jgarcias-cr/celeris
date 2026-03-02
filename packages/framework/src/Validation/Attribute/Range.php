<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Implement range behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



