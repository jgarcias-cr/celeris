<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement required behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when required functionality is required.
 */
final class Required
{
   /**
    * Create a new instance.
    *
    * @param bool $allowEmptyString
    * @return mixed
    */
   public function __construct(public bool $allowEmptyString = false)
   {
   }
}



