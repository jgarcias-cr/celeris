<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox;

final class OutboxStatus
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const RETRY = 'retry';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const DEAD_LETTER = 'dead_letter';

    public static function normalize(string $value): string
    {
        $status = strtolower(trim($value));

        return match ($status) {
            self::PROCESSING => self::PROCESSING,
            self::RETRY => self::RETRY,
            self::SENT => self::SENT,
            self::FAILED => self::FAILED,
            self::DEAD_LETTER => self::DEAD_LETTER,
            default => self::PENDING,
        };
    }
}
