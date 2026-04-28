<?php

declare(strict_types=1);

namespace Celeris\Framework\Runtime;

use Celeris\Framework\Http\Cookies;
use Celeris\Framework\Http\HttpStatus;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use JsonException;
use RuntimeException;

/**
 * Bridge framework contracts with a native socket-based worker runtime integration.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class NativeHttpWorkerAdapter implements WorkerAdapterInterface
{
   private const CRLF = "\r\n";

   /** @var resource|null */
   private $server = null;
   /** @var resource|null */
   private $currentClient = null;

   private bool $started = false;
   private bool $stopRequested = false;
   private int $servedRequests = 0;
   private ?string $boundAddress = null;
   private int $boundPort = 0;

   /**
    * Create a new instance.
    *
    * @param string $host
    * @param int $port
    * @param float $acceptTimeoutSeconds
    * @param float $readTimeoutSeconds
    * @param int $maxHeaderLineBytes
    * @param int $maxHeaderBytes
    * @param int $maxBodyBytes
    * @param ?int $maxRequests
    * @param bool $installSignalHandlers
    * @return mixed
    */
   public function __construct(
      private string $host = '127.0.0.1',
      private int $port = 8080,
      private float $acceptTimeoutSeconds = 0.5,
      private float $readTimeoutSeconds = 5.0,
      private int $maxHeaderLineBytes = 8192,
      private int $maxHeaderBytes = 65536,
      private int $maxBodyBytes = 1048576,
      private ?int $maxRequests = null,
      private bool $installSignalHandlers = true,
   ) {
      if (trim($this->host) === '') {
         throw new RuntimeException('NativeHttpWorkerAdapter host cannot be empty.');
      }
      if ($this->port < 0 || $this->port > 65535) {
         throw new RuntimeException('NativeHttpWorkerAdapter port must be between 0 and 65535.');
      }
      if ($this->acceptTimeoutSeconds <= 0) {
         throw new RuntimeException('NativeHttpWorkerAdapter accept timeout must be greater than zero.');
      }
      if ($this->readTimeoutSeconds <= 0) {
         throw new RuntimeException('NativeHttpWorkerAdapter read timeout must be greater than zero.');
      }
      if ($this->maxHeaderLineBytes < 128) {
         throw new RuntimeException('NativeHttpWorkerAdapter max header line bytes must be at least 128.');
      }
      if ($this->maxHeaderBytes < $this->maxHeaderLineBytes) {
         throw new RuntimeException('NativeHttpWorkerAdapter max header bytes must be >= max header line bytes.');
      }
      if ($this->maxBodyBytes < 0) {
         throw new RuntimeException('NativeHttpWorkerAdapter max body bytes cannot be negative.');
      }
      if ($this->maxRequests !== null && $this->maxRequests < 1) {
         throw new RuntimeException('NativeHttpWorkerAdapter max requests must be >= 1 when provided.');
      }
   }

   /**
    * Handle start.
    *
    * @return void
    */
   public function start(): void
   {
      if ($this->started) {
         return;
      }

      $endpoint = sprintf('tcp://%s:%d', $this->host, $this->port);
      $errno = 0;
      $error = '';
      $server = @stream_socket_server(
         $endpoint,
         $errno,
         $error,
         STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
      );

      if (!is_resource($server)) {
         throw new RuntimeException(sprintf(
            'Failed to start native worker socket on %s (%d: %s).',
            $endpoint,
            $errno,
            $error !== '' ? $error : 'unknown error'
         ));
      }

      stream_set_blocking($server, true);
      $this->server = $server;
      $this->started = true;

      $bound = stream_socket_get_name($server, false);
      if (is_string($bound) && $bound !== '') {
         $this->boundAddress = $bound;
         [, $this->boundPort] = self::splitAddress($bound);
      } else {
         $this->boundPort = $this->port;
      }

      $this->registerSignalHandlers();
   }

   /**
    * Handle next request.
    *
    * @return ?RuntimeRequest
    */
   public function nextRequest(): ?RuntimeRequest
   {
      $this->ensureStarted();

      while (true) {
         if ($this->stopRequested) {
            return null;
         }
         if ($this->maxRequests !== null && $this->servedRequests >= $this->maxRequests) {
            return null;
         }

         $client = @stream_socket_accept($this->server, $this->acceptTimeoutSeconds);
         if (!is_resource($client)) {
            continue;
         }

         $runtimeRequest = $this->decodeRuntimeRequest($client);
         if (!$runtimeRequest instanceof RuntimeRequest) {
            continue;
         }

         $this->servedRequests++;
         $this->currentClient = $client;
         return $runtimeRequest;
      }
   }

   /**
    * Handle send.
    *
    * @param RuntimeRequest $request
    * @param Response $response
    * @return void
    */
   public function send(RuntimeRequest $request, Response $response): void
   {
      $transport = $request->getTransport();
      $client = is_array($transport) ? ($transport['client'] ?? null) : null;
      if (!is_resource($client)) {
         return;
      }

      $status = $response->getStatus();
      $reason = self::reasonPhrase($status);
      $requestMethod = strtoupper($request->getRequest()->getMethod());
      $body = $response->getBody();

      $headers = $response->headers()->toMultiValueArray();
      if (!isset($headers['date'])) {
         $headers['date'] = [gmdate('D, d M Y H:i:s') . ' GMT'];
      }
      $headers['connection'] = ['close'];
      if (!isset($headers['content-length'])) {
         $headers['content-length'] = [(string) strlen($body)];
      }

      $payload = sprintf('HTTP/1.1 %d %s%s', $status, $reason, self::CRLF);
      foreach ($headers as $name => $values) {
         foreach ($values as $value) {
            $payload .= sprintf('%s: %s%s', $name, $value, self::CRLF);
         }
      }
      foreach ($response->getCookies() as $cookie) {
         $payload .= 'Set-Cookie: ' . $cookie->toHeaderValue() . self::CRLF;
      }
      $payload .= self::CRLF;

      $this->writeAll($client, $payload);
      if ($requestMethod !== 'HEAD') {
         $this->writeAll($client, $body);
      }

      fclose($client);
      if ($this->currentClient === $client) {
         $this->currentClient = null;
      }
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      if (is_resource($this->currentClient)) {
         fclose($this->currentClient);
      }
      $this->currentClient = null;
   }

   /**
    * Handle stop.
    *
    * @return void
    */
   public function stop(): void
   {
      $this->stopRequested = true;
      $this->reset();

      if (is_resource($this->server)) {
         fclose($this->server);
      }

      $this->server = null;
      $this->started = false;
   }

   /**
    * Handle listening address.
    *
    * @return ?string
    */
   public function listeningAddress(): ?string
   {
      return $this->boundAddress;
   }

   /**
    * Handle listening port.
    *
    * @return int
    */
   public function listeningPort(): int
   {
      return $this->boundPort !== 0 ? $this->boundPort : $this->port;
   }

   /**
    * Handle decode runtime request.
    * @param resource $client
    * @return ?RuntimeRequest
    */
   private function decodeRuntimeRequest(mixed $client): ?RuntimeRequest
   {
      $this->applyReadTimeout($client);

      $startLine = $this->readLine($client);
      if ($startLine === null || $startLine === '') {
         fclose($client);
         return null;
      }

      if (!preg_match('/^([A-Z]+)\s+(\S+)\s+HTTP\/(\d\.\d)$/', $startLine, $matches)) {
         $this->writeErrorAndClose($client, 400, 'Bad Request');
         return null;
      }

      $method = $matches[1];
      $target = $matches[2];
      $protocolVersion = $matches[3];

      $headers = [];
      $headerBytes = 0;
      while (true) {
         $line = $this->readLine($client);
         if ($line === null) {
            fclose($client);
            return null;
         }
         if ($line === '') {
            break;
         }

         $headerBytes += strlen($line);
         if ($headerBytes > $this->maxHeaderBytes) {
            $this->writeErrorAndClose($client, 431, 'Request Header Fields Too Large');
            return null;
         }

         $separator = strpos($line, ':');
         if ($separator === false) {
            $this->writeErrorAndClose($client, 400, 'Bad Request');
            return null;
         }

         $name = strtolower(trim(substr($line, 0, $separator)));
         $value = trim(substr($line, $separator + 1));
         if ($name === '') {
            $this->writeErrorAndClose($client, 400, 'Bad Request');
            return null;
         }

         $headers[$name] ??= [];
         $headers[$name][] = $value;
      }

      if (isset($headers['transfer-encoding']) && in_array('chunked', array_map('strtolower', $headers['transfer-encoding']), true)) {
         $this->writeErrorAndClose($client, 501, 'Not Implemented');
         return null;
      }

      $contentLength = $this->resolveContentLength($headers['content-length'][0] ?? null);
      if ($contentLength < 0) {
         $this->writeErrorAndClose($client, 400, 'Bad Request');
         return null;
      }
      if ($contentLength > $this->maxBodyBytes) {
         $this->writeErrorAndClose($client, 413, 'Payload Too Large');
         return null;
      }

      $body = '';
      if ($contentLength > 0) {
         $body = $this->readBytes($client, $contentLength);
         if (strlen($body) !== $contentLength) {
            $this->writeErrorAndClose($client, 408, 'Request Timeout');
            return null;
         }
      }

      $path = parse_url($target, PHP_URL_PATH);
      $path = is_string($path) && $path !== '' ? $path : '/';

      $queryParams = [];
      $query = parse_url($target, PHP_URL_QUERY);
      if (is_string($query) && $query !== '') {
         parse_str($query, $queryParams);
      }

      $cookies = Cookies::fromCookieHeader($headers['cookie'][0] ?? null)->all();
      $parsedBody = $this->resolveParsedBody($headers, $body);
      $serverParams = $this->buildServerParams($method, $target, $protocolVersion, $client);

      $request = new Request(
         $method,
         $path,
         $headers,
         $queryParams,
         $body,
         $cookies,
         [],
         $parsedBody,
         $serverParams,
      );

      $context = new RequestContext(
         bin2hex(random_bytes(8)),
         microtime(true),
         $serverParams,
      );

      return new RuntimeRequest(
         $context,
         $request,
         [
            'client' => $client,
            'protocol_version' => $protocolVersion,
            'local_address' => stream_socket_get_name($client, false) ?: null,
            'remote_address' => stream_socket_get_name($client, true) ?: null,
         ]
      );
   }

   /**
    * Handle resolve parsed body.
    *
    * @param array<string, array<int, string>> $headers
    * @return mixed
    */
   private function resolveParsedBody(array $headers, string $body): mixed
   {
      $contentType = strtolower(trim((string) ($headers['content-type'][0] ?? '')));

      if ($body === '') {
         return null;
      }

      if (str_contains($contentType, 'application/json')) {
         try {
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
         } catch (JsonException) {
            return null;
         }
      }

      if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
         $data = [];
         parse_str($body, $data);
         return $data;
      }

      return null;
   }

   /**
    * Handle resolve content length.
    * @param array<string, array<int, string>> $headers
    * @return int
    */
   private function resolveContentLength(?string $raw): int
   {
      if ($raw === null || $raw === '') {
         return 0;
      }

      $clean = trim($raw);
      if ($clean === '' || !ctype_digit($clean)) {
         return -1;
      }

      return (int) $clean;
   }

   /**
    * Handle build server params.
    *
    * @param string $method
    * @param string $target
    * @param string $protocolVersion
    * @param resource $client
    * @return array<string, mixed>
    */
   private function buildServerParams(string $method, string $target, string $protocolVersion, mixed $client): array
   {
      $remote = stream_socket_get_name($client, true);
      $local = stream_socket_get_name($client, false);

      [$remoteAddr, $remotePort] = self::splitAddress(is_string($remote) ? $remote : '');
      [$serverAddr, $serverPort] = self::splitAddress(is_string($local) ? $local : '');

      return [
         'REQUEST_METHOD' => $method,
         'REQUEST_URI' => $target,
         'SERVER_PROTOCOL' => 'HTTP/' . $protocolVersion,
         'REMOTE_ADDR' => $remoteAddr,
         'REMOTE_PORT' => $remotePort,
         'SERVER_ADDR' => $serverAddr,
         'SERVER_PORT' => $serverPort,
      ];
   }

   /**
    * Handle read line.
    *
    * @param resource $client
    * @return ?string
    */
   private function readLine(mixed $client): ?string
   {
      $line = fgets($client, $this->maxHeaderLineBytes + 2);
      if ($line === false) {
         return null;
      }
      if (!str_ends_with($line, "\n") && !feof($client)) {
         return null;
      }

      return rtrim($line, "\r\n");
   }

   /**
    * Handle read bytes.
    *
    * @param resource $client
    * @param int $length
    * @return string
    */
   private function readBytes(mixed $client, int $length): string
   {
      $buffer = '';
      while (strlen($buffer) < $length) {
         $remaining = $length - strlen($buffer);
         $chunk = fread($client, $remaining);
         if ($chunk === false || $chunk === '') {
            $meta = stream_get_meta_data($client);
            if (($meta['timed_out'] ?? false) === true) {
               break;
            }
            if (($meta['eof'] ?? false) === true) {
               break;
            }
            continue;
         }
         $buffer .= $chunk;
      }

      return $buffer;
   }

   /**
    * Handle write all.
    *
    * @param resource $stream
    * @param string $payload
    * @return void
    */
   private function writeAll(mixed $stream, string $payload): void
   {
      $offset = 0;
      $length = strlen($payload);

      while ($offset < $length) {
         $written = fwrite($stream, substr($payload, $offset));
         if (!is_int($written) || $written <= 0) {
            return;
         }
         $offset += $written;
      }
   }

   /**
    * Handle write error and close.
    * @param resource $client
    */
   private function writeErrorAndClose(mixed $client, int $status, string $reason): void
   {
      $body = $reason;
      $payload = sprintf('HTTP/1.1 %d %s%s', $status, $reason, self::CRLF)
         . 'content-type: text/plain; charset=utf-8' . self::CRLF
         . 'content-length: ' . strlen($body) . self::CRLF
         . 'connection: close' . self::CRLF
         . 'date: ' . gmdate('D, d M Y H:i:s') . ' GMT' . self::CRLF
         . self::CRLF
         . $body;

      $this->writeAll($client, $payload);
      fclose($client);
   }

   /**
    * Handle ensure started.
    *
    * @return void
    */
   private function ensureStarted(): void
   {
      if (!$this->started || !is_resource($this->server)) {
         throw new RuntimeException('NativeHttpWorkerAdapter must be started before receiving requests.');
      }
   }

   /**
    * Handle apply read timeout.
    *
    * @param resource $stream
    */
   private function applyReadTimeout(mixed $stream): void
   {
      $seconds = (int) floor($this->readTimeoutSeconds);
      $micros = (int) (($this->readTimeoutSeconds - $seconds) * 1_000_000);
      stream_set_timeout($stream, max($seconds, 0), max($micros, 0));
   }

   /**
    * Handle register signal handlers.
    *
    * @return void
    */
   private function registerSignalHandlers(): void
   {
      if (!$this->installSignalHandlers) {
         return;
      }
      if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
         return;
      }

      pcntl_async_signals(true);
      if (defined('SIGTERM')) {
         pcntl_signal(SIGTERM, function (): void {
            $this->stopRequested = true;
         });
      }
      if (defined('SIGINT')) {
         pcntl_signal(SIGINT, function (): void {
            $this->stopRequested = true;
         });
      }
   }

   /**
    * Handle split address.
    *
    * @param string $address
    * @return array{string, int}
    */
   private static function splitAddress(string $address): array
   {
      $trimmed = trim($address);
      if ($trimmed === '') {
         return ['', 0];
      }

      if (preg_match('/^\[(.+)\]:(\d+)$/', $trimmed, $matches) === 1) {
         return [$matches[1], (int) $matches[2]];
      }

      $separator = strrpos($trimmed, ':');
      if ($separator === false) {
         return [$trimmed, 0];
      }

      $host = substr($trimmed, 0, $separator);
      $port = substr($trimmed, $separator + 1);

      return [$host, ctype_digit($port) ? (int) $port : 0];
   }

   /**
    * Handle reason phrase.
    *
    * @param int $status
    * @return string
    */
   private static function reasonPhrase(int $status): string
   {
      $known = HttpStatus::tryFrom($status);
      if ($known instanceof HttpStatus) {
         return $known->reasonPhrase();
      }

      return match ($status) {
         408 => 'Request Timeout',
         413 => 'Payload Too Large',
         431 => 'Request Header Fields Too Large',
         501 => 'Not Implemented',
         default => 'Unknown Status',
      };
   }
}
