<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway\Http;

final class StreamHttpGatewayTransport implements HttpGatewayTransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, array $headers, string $body, int $timeoutSeconds): HttpGatewayResponse
    {
        $requestHeaders = [];
        foreach ($headers as $name => $value) {
            $requestHeaders[] = trim($name) . ': ' . trim($value);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $requestHeaders),
                'content' => $body,
                'timeout' => max(1, $timeoutSeconds),
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $headerLines = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];

        $statusCode = self::extractStatusCode($headerLines);
        $parsedHeaders = self::extractHeaders($headerLines);

        if ($responseBody === false && $statusCode === 0) {
            $error = error_get_last();
            $message = is_array($error) ? (string) ($error['message'] ?? 'Unknown transport error.') : 'Unknown transport error.';
            throw new \RuntimeException($message);
        }

        return new HttpGatewayResponse($statusCode, $responseBody !== false ? $responseBody : '', $parsedHeaders);
    }

    /**
     * @param array<int, string> $headerLines
     */
    private static function extractStatusCode(array $headerLines): int
    {
        foreach ($headerLines as $line) {
            if (preg_match('#^HTTP/\d+(?:\.\d+)?\s+(\d{3})#i', $line, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    /**
     * @param array<int, string> $headerLines
     * @return array<string, string>
     */
    private static function extractHeaders(array $headerLines): array
    {
        $headers = [];
        foreach ($headerLines as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            if ($key === '') {
                continue;
            }

            $headers[$key] = trim($value);
        }

        return $headers;
    }
}
