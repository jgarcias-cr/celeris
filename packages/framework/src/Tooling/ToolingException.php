<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling;

use RuntimeException;

/**
 * Represent a domain-specific failure for Tooling operations.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ToolingException extends RuntimeException
{
}


