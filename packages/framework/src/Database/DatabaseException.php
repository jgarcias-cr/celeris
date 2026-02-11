<?php

declare(strict_types=1);

namespace Celeris\Framework\Database;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Database operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by database components and surfaced through kernel error handling.
 */
class DatabaseException extends RuntimeException
{
}


