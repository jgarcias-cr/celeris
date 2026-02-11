<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement pattern behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when pattern functionality is required.
 */
final class Pattern
{
   /**
    * Create a new instance.
    *
    * @param string $regex
    * @return mixed
    */
   public function __construct(public string $regex)
   {
   }
}



