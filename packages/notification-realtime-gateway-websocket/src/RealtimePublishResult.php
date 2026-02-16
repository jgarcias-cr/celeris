<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

final class RealtimePublishResult
{
    public function __construct(
        private bool $published,
        private string $failureType = RealtimeFailureType::NONE,
        private ?int $statusCode = null,
        private ?string $message = null,
        private ?int $retryAfterSeconds = null,
        private array $metadata = [],
    ) {
    }

    public static function published(int $statusCode, string $message = '', array $metadata = []): self
    {
        return new self(true, RealtimeFailureType::NONE, $statusCode, $message, null, $metadata);
    }

    public static function retryableFailure(string $message, ?int $statusCode = null, ?int $retryAfterSeconds = null, array $metadata = []): self
    {
        return new self(false, RealtimeFailureType::RETRYABLE, $statusCode, $message, $retryAfterSeconds, $metadata);
    }

    public static function terminalFailure(string $message, ?int $statusCode = null, array $metadata = []): self
    {
        return new self(false, RealtimeFailureType::TERMINAL, $statusCode, $message, null, $metadata);
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function isRetryable(): bool
    {
        return $this->failureType === RealtimeFailureType::RETRYABLE;
    }

    public function failureType(): string
    {
        return $this->failureType;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'published' => $this->published,
            'failure_type' => $this->failureType,
            'status_code' => $this->statusCode,
            'message' => $this->message,
            'retry_after_seconds' => $this->retryAfterSeconds,
            'metadata' => $this->metadata,
        ];
    }
}
