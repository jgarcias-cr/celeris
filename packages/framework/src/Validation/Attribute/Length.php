<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Implement length behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



