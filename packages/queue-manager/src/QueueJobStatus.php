<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

final class QueueJobStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const RETRY = 'retry';
    public const SUCCEEDED = 'succeeded';
    public const FAILED = 'failed';
    public const DEAD_LETTER = 'dead_letter';

    public static function normalize(string $value): string
    {
        $status = strtolower(trim($value));

        return match ($status) {
            self::PROCESSING => self::PROCESSING,
            self::RETRY => self::RETRY,
            self::SUCCEEDED => self::SUCCEEDED,
            self::FAILED => self::FAILED,
            self::DEAD_LETTER => self::DEAD_LETTER,
            default => self::PENDING,
        };
    }
}
