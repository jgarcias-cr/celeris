<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
/**
 * Purpose: implement dto behavior for the Serialization subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by serialization components when dto functionality is required.
 */
final class Dto
{
}


