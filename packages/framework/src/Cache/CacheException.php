<?php

declare(strict_types=1);

namespace Celeris\Framework\Cache;

use RuntimeException;

/**
 * Represent a domain-specific failure for Cache operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
class CacheException extends RuntimeException
{
}


