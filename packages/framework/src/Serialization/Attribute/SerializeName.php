<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Purpose: implement serialize name behavior for the Serialization subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by serialization components when serialize name functionality is required.
 */
final class SerializeName
{
   /**
    * Create a new instance.
    *
    * @param string $name
    * @return mixed
    */
   public function __construct(public string $name)
   {
   }
}



