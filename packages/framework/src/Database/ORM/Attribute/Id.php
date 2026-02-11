<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Purpose: implement id behavior for the Database subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by database components when id functionality is required.
 */
final class Id
{
   /**
    * Create a new instance.
    *
    * @param bool $generated
    * @return mixed
    */
   public function __construct(public bool $generated = true)
   {
   }
}



