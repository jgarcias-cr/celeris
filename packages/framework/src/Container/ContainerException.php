<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

use RuntimeException;

/**
 * Represent a domain-specific failure for Container operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
class ContainerException extends RuntimeException
{
}



