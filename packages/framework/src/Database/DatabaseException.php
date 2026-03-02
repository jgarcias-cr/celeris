<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

use RuntimeException;

/**
 * Represent a domain-specific failure for Database operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
class DatabaseException extends RuntimeException
{
}


