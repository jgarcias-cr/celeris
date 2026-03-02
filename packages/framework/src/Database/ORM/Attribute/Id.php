<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ORM\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
/**
 * Implement id behavior for the Database subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Id
{
   /**
    * Create a new instance.
    *
    * @param bool $generated
    * @param string $strategy
    * @param ?string $sequence
    * @return mixed
    */
   public function __construct(
      public bool $generated = true,
      public string $strategy = 'auto',
      public ?string $sequence = null,
   )
   {
   }
}


