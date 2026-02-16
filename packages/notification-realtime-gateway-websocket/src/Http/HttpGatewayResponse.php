<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway\Http;

final class HttpGatewayResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        private int $statusCode,
        private string $body,
        private array $headers = [],
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $key = strtolower(trim($name));
        if ($key === '') {
            return null;
        }

        return $this->headers[$key] ?? null;
    }
}
