<?php

declare(strict_types=1);

namespace Celeris\Framework\Container;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Container operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by container components and surfaced through kernel error handling.
 */
class ContainerException extends RuntimeException
{
}



