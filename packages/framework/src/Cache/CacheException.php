<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Cache operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by cache components and surfaced through kernel error handling.
 */
class CacheException extends RuntimeException
{
}


