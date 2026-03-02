<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Implement in list behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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


