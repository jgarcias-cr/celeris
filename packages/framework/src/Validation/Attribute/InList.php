<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement in list behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when in list functionality is required.
 */
final class InList
{
   /** @var array<int, string|int|float|bool> */
   public array $values;

   /**
    * @param array<int, string|int|float|bool> $values
    */
   public function __construct(array $values)
   {
      $this->values = $values;
   }
}


