<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway\Http;

interface HttpGatewayTransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpGatewayResponse;
}
