<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Implement serialize name behavior for the Serialization subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



