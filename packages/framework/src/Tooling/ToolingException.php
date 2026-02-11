<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling;

use RuntimeException;

/**
 * Purpose: represent a domain-specific failure for Tooling operations.
 * How: extends the exception model with context that callers and handlers can inspect.
 * Used in framework: thrown by tooling components and surfaced through kernel error handling.
 */
final class ToolingException extends RuntimeException
{
}


