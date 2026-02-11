<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
/**
 * Purpose: implement map from behavior for the Serialization subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by serialization components when map from functionality is required.
 */
final class MapFrom
{
   /**
    * Create a new instance.
    *
    * @param string $field
    * @return mixed
    */
   public function __construct(public string $field)
   {
   }
}



