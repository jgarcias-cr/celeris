<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use JsonException;

/**
 * Immutable HTTP request value object used by middleware and handlers.
 *
 * It carries transport-level input (method, path, headers, query/body, cookies, files,
 * and server params) from runtime adapters into the kernel pipeline. `withXxx` methods
 * return cloned instances to keep request state predictable in worker mode.
 */
final class Request
{
   private string $method;
   private string $path;
   private Headers $headers;
   /** @var array<string, mixed> */
   private array $queryParams;
   /** @var array<string, mixed> */
   private array $serverParams;
   private Cookies $cookies;
   /** @var array<string, UploadedFile|array> */
   private array $uploadedFiles;
   private mixed $parsedBody;
   private string $body;

   /**
    * @param Headers|array<string, string|array<int, string>> $headers
    * @param array<string, mixed> $queryParams
    * @param array<string, string>|Cookies $cookies
    * @param array<string, UploadedFile|array> $uploadedFiles
    * @param array<string, mixed> $serverParams
    */
   public function __construct(
      string $method,
      string $path,
      Headers|array $headers = [],
      array $queryParams = [],
      string $body = '',
      Cookies|array $cookies = [],
      array $uploadedFiles = [],
      mixed $parsedBody = null,
      array $serverParams = [],
   ) {
      $this->method = strtoupper($method);
      $this->path = $path !== '' ? $path : '/';
      $this->headers = $headers instanceof Headers ? $headers : Headers::fromArray($headers);
      $this->queryParams = $queryParams;
      $this->body = $body;
      $this->cookies = $cookies instanceof Cookies ? $cookies : Cookies::fromArray($cookies);
      $this->uploadedFiles = $uploadedFiles;
      $this->parsedBody = $parsedBody;
      $this->serverParams = $serverParams;
   }

   /**
    * @param array<string, mixed>|null $server
    * @param array<string, mixed>|null $query
    * @param array<string, string|array<int, string>>|null $headers
    * @param array<string, string>|null $cookies
    * @param array<string, mixed>|null $files
    */
   public static function fromGlobals(
      ?array $server = null,
      ?array $query = null,
      ?string $body = null,
      ?array $headers = null,
      ?array $cookies = null,
      ?array $files = null,
      mixed $parsedBody = null
   ): self {
      $serverData = $server ?? $_SERVER;
      $queryData = $query ?? $_GET;
      $method = (string) ($serverData['REQUEST_METHOD'] ?? 'GET');
      $uri = (string) ($serverData['REQUEST_URI'] ?? '/');
      $path = parse_url($uri, PHP_URL_PATH);
      $rawBody = $body ?? (string) file_get_contents('php://input');
      $requestHeaders = $headers ?? self::extractHeaders($serverData);
      $requestCookies = $cookies ?? ($_COOKIE ?? []);
      $requestFiles = $files ?? ($_FILES ?? []);
      $resolvedParsedBody = self::resolveParsedBody($parsedBody, $rawBody, $requestHeaders);

      return new self(
         $method,
         is_string($path) && $path !== '' ? $path : '/',
         $requestHeaders,
         $queryData,
         $rawBody,
         $requestCookies,
         self::normalizeUploadedFiles($requestFiles),
         $resolvedParsedBody,
         $serverData,
      );
   }

   /**
    * Get the method.
    *
    * @return string
    */
   public function getMethod(): string
   {
      return $this->method;
   }

   /**
    * Get the path.
    *
    * @return string
    */
   public function getPath(): string
   {
      return $this->path;
   }

   /**
    * Return a copy with the method.
    *
    * @param string $method
    * @return self
    */
   public function withMethod(string $method): self
   {
      $copy = clone $this;
      $copy->method = strtoupper($method);
      return $copy;
   }

   /**
    * Return a copy with the path.
    *
    * @param string $path
    * @return self
    */
   public function withPath(string $path): self
   {
      $copy = clone $this;
      $copy->path = $path !== '' ? $path : '/';
      return $copy;
   }

   /** 
    * Get the headers.
    *
    * @return array<string, string|array<int, string>> 
    */
   public function getHeaders(): array
   {
      return $this->headers->toArray();
   }

   /**
    * Handle headers.
    *
    * @return Headers
    */
   public function headers(): Headers
   {
      return $this->headers;
   }

   /**
    * Get the header.
    *
    * @param string $name
    * @param ?string $default
    * @return ?string
    */
   public function getHeader(string $name, ?string $default = null): ?string
   {
      return $this->headers->first($name, $default);
   }

   /** 
    * Get the query params.
    *
    * @return array<string, mixed> 
    */
   public function getQueryParams(): array
   {
      return $this->queryParams;
   }

   /**
    * Get the query param.
    *
    * @param string $name
    * @param mixed $default
    * @return mixed
    */
   public function getQueryParam(string $name, mixed $default = null): mixed
   {
      return $this->queryParams[$name] ?? $default;
   }

   /**
    * Return a copy with the query params.
    *
    * @param array $queryParams
    * @return self
    */
   public function withQueryParams(array $queryParams): self
   {
      $copy = clone $this;
      $copy->queryParams = $queryParams;
      return $copy;
   }

   /**
    * Return a copy with the headers.
    *
    * @param Headers|array<string, string|array<int, string>> $headers
    */
   public function withHeaders(Headers|array $headers): self
   {
      $copy = clone $this;
      $copy->headers = $headers instanceof Headers ? $headers : Headers::fromArray($headers);
      return $copy;
   }

   /**
    * Get the body.
    *
    * @return string
    */
   public function getBody(): string
   {
      return $this->body;
   }

   /**
    * Return a copy with the body.
    *
    * @param string $body
    * @return self
    */
   public function withBody(string $body): self
   {
      $copy = clone $this;
      $copy->body = $body;
      return $copy;
   }

   /**
    * Return a copy with the cookies.
    * @param array<string, string>|Cookies $cookies
    */
   public function withCookies(Cookies|array $cookies): self
   {
      $copy = clone $this;
      $copy->cookies = $cookies instanceof Cookies ? $cookies : Cookies::fromArray($cookies);
      return $copy;
   }

   /**
    * Get the cookies.
    *
    * @return Cookies
    */
   public function getCookies(): Cookies
   {
      return $this->cookies;
   }

   /** @return array<string, UploadedFile|array> */
   public function getUploadedFiles(): array
   {
      return $this->uploadedFiles;
   }

   /**
    * Get the uploaded file.
    *
    * @param string $name
    * @return UploadedFile|array|null
    */
   public function getUploadedFile(string $name): UploadedFile|array|null
   {
      return $this->uploadedFiles[$name] ?? null;
   }

   /**
    * Get the parsed body.
    *
    * @return mixed
    */
   public function getParsedBody(): mixed
   {
      return $this->parsedBody;
   }

   /**
    * Return a copy with the parsed body.
    *
    * @param mixed $parsedBody
    * @return self
    */
   public function withParsedBody(mixed $parsedBody): self
   {
      $copy = clone $this;
      $copy->parsedBody = $parsedBody;
      return $copy;
   }

   /**
    * Return a copy with the uploaded files.
    * @param array<string, UploadedFile|array> $uploadedFiles
    */
   public function withUploadedFiles(array $uploadedFiles): self
   {
      $copy = clone $this;
      $copy->uploadedFiles = $uploadedFiles;
      return $copy;
   }

   /** 
    * Get the server params.
    *
    * @return array<string, mixed> 
    */
   public function getServerParams(): array
   {
      return $this->serverParams;
   }

   /**
    * Return a copy with the server params.
    * @param array<string, mixed> $serverParams
    */
   public function withServerParams(array $serverParams): self
   {
      $copy = clone $this;
      $copy->serverParams = $serverParams;
      return $copy;
   }

   /**
    * Handle negotiate content type.
    *
    * @param array $supported
    * @param ?string $default
    * @return ?string
    */
   public function negotiateContentType(array $supported, ?string $default = null): ?string
   {
      return ContentNegotiator::negotiate($supported, $this->getHeader('accept'), $default);
   }

   /**
    * Handle accepts.
    *
    * @param string $contentType
    * @return bool
    */
   public function accepts(string $contentType): bool
   {
      return ContentNegotiator::accepts($contentType, $this->getHeader('accept'));
   }

   /**
    * Handle extract headers.
    * @param array<string, mixed> $server
    * @return array<string, string|array<int, string>>
    */
   private static function extractHeaders(array $server): array
   {
      $headers = [];
      foreach ($server as $key => $value) {
         if (!is_string($key)) {
            continue;
         }

         if (str_starts_with($key, 'HTTP_')) {
            $normalized = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$normalized] = (string) $value;
         }
      }

      if (isset($server['CONTENT_TYPE'])) {
         $headers['content-type'] = (string) $server['CONTENT_TYPE'];
      }
      if (isset($server['CONTENT_LENGTH'])) {
         $headers['content-length'] = (string) $server['CONTENT_LENGTH'];
      }

      return $headers;
   }

   /**
    * Handle normalize uploaded files.
    * @param array<string, mixed> $files
    * @return array<string, UploadedFile|array>
    */
   private static function normalizeUploadedFiles(array $files): array
   {
      $normalized = [];
      foreach ($files as $field => $spec) {
         if (!is_array($spec)) {
            continue;
         }

         if (self::isFlatUploadedFileSpec($spec)) {
            $normalized[(string) $field] = UploadedFile::fromPhpSpec($spec);
            continue;
         }

         if (self::isNestedUploadedFileSpec($spec)) {
            /** @var array{name:mixed,type:mixed,tmp_name:mixed,error:mixed,size:mixed} $spec */
            $normalized[(string) $field] = self::expandUploadedFileTree(
               $spec['name'],
               $spec['type'],
               $spec['tmp_name'],
               $spec['error'],
               $spec['size'],
            );
         }
      }

      return $normalized;
   }

   /**
    * Determine whether is flat uploaded file spec.
    *
    * @param array $spec
    * @return bool
    */
   private static function isFlatUploadedFileSpec(array $spec): bool
   {
      return isset($spec['name'], $spec['type'], $spec['tmp_name'], $spec['error'], $spec['size'])
         && !is_array($spec['name']);
   }

   /**
    * Determine whether is nested uploaded file spec.
    *
    * @param array $spec
    * @return bool
    */
   private static function isNestedUploadedFileSpec(array $spec): bool
   {
      return isset($spec['name'], $spec['type'], $spec['tmp_name'], $spec['error'], $spec['size'])
         && is_array($spec['name']);
   }

   /**
    * Handle expand uploaded file tree.
    *
    * @param mixed $nameNode
    * @param mixed $typeNode
    * @param mixed $tmpNode
    * @param mixed $errorNode
    * @param mixed $sizeNode
    * @return UploadedFile|array
    */
   private static function expandUploadedFileTree(
      mixed $nameNode,
      mixed $typeNode,
      mixed $tmpNode,
      mixed $errorNode,
      mixed $sizeNode
   ): UploadedFile|array {
      if (!is_array($nameNode)) {
         return UploadedFile::fromPhpSpec([
            'name' => (string) $nameNode,
            'type' => (string) $typeNode,
            'tmp_name' => (string) $tmpNode,
            'error' => (int) $errorNode,
            'size' => (int) $sizeNode,
         ]);
      }

      $result = [];
      foreach ($nameNode as $index => $nameValue) {
         $result[$index] = self::expandUploadedFileTree(
            $nameValue,
            is_array($typeNode) ? ($typeNode[$index] ?? '') : $typeNode,
            is_array($tmpNode) ? ($tmpNode[$index] ?? '') : $tmpNode,
            is_array($errorNode) ? ($errorNode[$index] ?? UPLOAD_ERR_NO_FILE) : $errorNode,
            is_array($sizeNode) ? ($sizeNode[$index] ?? 0) : $sizeNode,
         );
      }

      return $result;
   }

   /**
    * Handle resolve parsed body.
    * @param array<string, string|array<int, string>> $headers
    */
   private static function resolveParsedBody(mixed $parsedBody, string $rawBody, array $headers): mixed
   {
      if ($parsedBody !== null) {
         return $parsedBody;
      }

      $contentType = Headers::fromArray($headers)->first('content-type', '');
      if (str_contains(strtolower((string) $contentType), 'application/json')) {
         if ($rawBody === '') {
            return null;
         }

         try {
            return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
         } catch (JsonException) {
            return null;
         }
      }

      return null;
   }
}


