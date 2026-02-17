<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Http\Response;
use Celeris\Framework\Runtime\NativeHttpWorkerAdapter;

/**
 * Handle assert true.
 *
 * @param bool $condition
 * @param string $message
 * @return void
 */
function assertTrue(bool $condition, string $message): void
{
   if (!$condition) {
      throw new RuntimeException($message);
   }
}

$adapter = new NativeHttpWorkerAdapter(
   host: '127.0.0.1',
   port: 0,
   acceptTimeoutSeconds: 0.2,
   readTimeoutSeconds: 2.0,
   installSignalHandlers: false,
);
try {
   $adapter->start();
} catch (RuntimeException $exception) {
   if (str_contains($exception->getMessage(), 'Failed to start native worker socket')) {
      echo "native_http_worker_validation: skipped (socket bind unavailable)\n";
      exit(0);
   }

   throw $exception;
}

$port = $adapter->listeningPort();
assertTrue($port > 0, 'Native adapter should bind an ephemeral local port.');

$client = @stream_socket_client(
   sprintf('tcp://127.0.0.1:%d', $port),
   $errno,
   $error,
   2.0
);
assertTrue(is_resource($client), sprintf('Failed to connect to native adapter (%d: %s).', $errno, $error));

$payload = '{"value":"abc"}';
$request = "POST /echo?mode=test HTTP/1.1\r\n"
   . "Host: 127.0.0.1\r\n"
   . "Content-Type: application/json\r\n"
   . "Content-Length: " . strlen($payload) . "\r\n"
   . "Cookie: session_id=s-1; theme=light\r\n"
   . "Connection: close\r\n"
   . "\r\n"
   . $payload;
fwrite($client, $request);

$runtimeRequest = $adapter->nextRequest();
assertTrue($runtimeRequest !== null, 'Native adapter should decode a runtime request frame.');

$decoded = $runtimeRequest->getRequest();
assertTrue($decoded->getMethod() === 'POST', 'Decoded request method should be POST.');
assertTrue($decoded->getPath() === '/echo', 'Decoded request path should match target path.');
assertTrue($decoded->getQueryParam('mode') === 'test', 'Decoded request should include query parameters.');
assertTrue($decoded->getHeader('content-type') === 'application/json', 'Decoded request should preserve content-type header.');
assertTrue($decoded->getCookies()->get('session_id') === 's-1', 'Decoded request should parse cookie header.');
assertTrue(is_array($decoded->getParsedBody()), 'Decoded request should parse JSON payload.');
assertTrue(($decoded->getParsedBody()['value'] ?? null) === 'abc', 'Decoded JSON payload should be accessible in parsed body.');

$response = new Response(
   201,
   [
      'content-type' => 'application/json; charset=utf-8',
      'x-native-worker' => 'yes',
   ],
   '{"ok":true}'
);
$adapter->send($runtimeRequest, $response);

$wire = '';
while (!feof($client)) {
   $chunk = fread($client, 8192);
   if ($chunk === false || $chunk === '') {
      break;
   }
   $wire .= $chunk;
}
fclose($client);

assertTrue(str_contains($wire, 'HTTP/1.1 201 Created'), 'Native adapter should emit HTTP status line.');
assertTrue(str_contains(strtolower($wire), 'x-native-worker: yes'), 'Native adapter should emit custom headers.');
assertTrue(str_contains($wire, '{"ok":true}'), 'Native adapter should emit response body payload.');
assertTrue(str_contains(strtolower($wire), 'connection: close'), 'Native adapter should force connection close in worker loop.');

$adapter->reset();
$adapter->stop();

echo "native_http_worker_validation: ok\n";
