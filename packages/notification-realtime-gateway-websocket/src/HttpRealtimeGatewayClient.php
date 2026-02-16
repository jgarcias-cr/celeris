<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;
use Celeris\Notification\RealtimeGateway\Http\HttpGatewayResponse;
use Celeris\Notification\RealtimeGateway\Http\HttpGatewayTransportInterface;
use Celeris\Notification\RealtimeGateway\Http\StreamHttpGatewayTransport;
use JsonException;
use Throwable;

final class HttpRealtimeGatewayClient implements RealtimeGatewayClientInterface
{
    private HttpGatewayTransportInterface $transport;

    public function __construct(
        private string $endpoint,
        private int $timeoutSeconds = 5,
        private string $serviceId = '',
        private string $serviceSecret = '',
        ?HttpGatewayTransportInterface $transport = null,
    ) {
        $this->endpoint = trim($this->endpoint);
        $this->timeoutSeconds = max(1, $this->timeoutSeconds);
        $this->serviceId = trim($this->serviceId);
        $this->serviceSecret = trim($this->serviceSecret);
        $this->transport = $transport ?? new StreamHttpGatewayTransport();
    }

    public function publish(RealtimeEventMessage $message): RealtimePublishResult
    {
        if ($this->endpoint === '') {
            return RealtimePublishResult::terminalFailure('Realtime gateway endpoint is not configured.');
        }

        if (trim($message->event()) === '' || trim($message->userId()) === '') {
            return RealtimePublishResult::terminalFailure('Realtime message requires non-empty event and user_id.');
        }

        try {
            $body = (string) json_encode($message->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            return RealtimePublishResult::terminalFailure('Failed to encode realtime payload: ' . $exception->getMessage());
        }

        $headers = [
            'content-type' => 'application/json; charset=utf-8',
            'accept' => 'application/json',
        ];

        foreach ($this->buildAuthHeaders($body) as $name => $value) {
            $headers[$name] = $value;
        }

        try {
            $response = $this->transport->post(
                $this->endpoint,
                $headers,
                $body,
                $this->timeoutSeconds,
            );
        } catch (Throwable $exception) {
            return RealtimePublishResult::retryableFailure(
                'Realtime gateway transport error: ' . $exception->getMessage(),
                null,
                null,
                ['endpoint' => $this->endpoint],
            );
        }

        return $this->classifyResponse($response);
    }

    /** @return array<string, string> */
    private function buildAuthHeaders(string $body): array
    {
        if ($this->serviceId === '' || $this->serviceSecret === '') {
            return [];
        }

        $timestamp = (string) time();
        $signaturePayload = $timestamp . '.' . $body;
        $signature = base64_encode(hash_hmac('sha256', $signaturePayload, $this->serviceSecret, true));

        return [
            'x-celeris-service-id' => $this->serviceId,
            'x-celeris-timestamp' => $timestamp,
            'x-celeris-signature' => $signature,
        ];
    }

    private function classifyResponse(HttpGatewayResponse $response): RealtimePublishResult
    {
        $status = $response->statusCode();
        $body = $response->body();

        if ($status >= 200 && $status < 300) {
            return RealtimePublishResult::published($status, $body, ['http_status' => $status]);
        }

        if ($status === 429 || $status >= 500 || $status === 0) {
            $retryAfter = $this->parseRetryAfter($response);
            return RealtimePublishResult::retryableFailure(
                sprintf('Realtime gateway returned retryable status %d.', $status),
                $status > 0 ? $status : null,
                $retryAfter,
                ['response_body' => $body],
            );
        }

        return RealtimePublishResult::terminalFailure(
            sprintf('Realtime gateway returned terminal status %d.', $status),
            $status,
            ['response_body' => $body],
        );
    }

    private function parseRetryAfter(HttpGatewayResponse $response): ?int
    {
        $value = $response->header('retry-after');
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            $seconds = (int) $value;
            return $seconds >= 0 ? $seconds : null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $delta = $timestamp - time();
        return $delta > 0 ? $delta : 0;
    }
}
