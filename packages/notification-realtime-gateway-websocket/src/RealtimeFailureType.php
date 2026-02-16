<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

final class RealtimeFailureType
{
    public const NONE = 'none';
    public const RETRYABLE = 'retryable';
    public const TERMINAL = 'terminal';
}
