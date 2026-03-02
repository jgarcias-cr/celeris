<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Per-request context bag carried through the kernel call chain.
 *
 * This object stores request metadata (request id, timing, route/auth context, and
 * attributes) without relying on global state. It is designed for explicit propagation
 * across middleware, handlers, and response finalizers.
 */
final class RequestContext
{
   private string $requestId;
   private float $timestamp;
   /** @var array<string, mixed> */
   private array $serverParams;
   /** @var array<string, mixed>|null */
   private ?array $auth;
   /** @var array<string, mixed> */
   private array $routeMetadata;
   private ?float $deadline;
   /** @var array<string, mixed> */
   private array $attributes;

   /**
    * @param array<string, mixed> $serverParams
    * @param array<string, mixed>|null $auth
    * @param array<string, mixed> $routeMetadata
    * @param array<string, mixed> $attributes
    */
   public function __construct(
      string $requestId,
      float $timestamp,
      array $serverParams = [],
      ?array $auth = null,
      array $routeMetadata = [],
      ?float $deadline = null,
      array $attributes = []
   )
   {
      $this->requestId = $requestId;
      $this->timestamp = $timestamp;
      $this->serverParams = $serverParams;
      $this->auth = $auth;
      $this->routeMetadata = $routeMetadata;
      $this->deadline = $deadline;
      $this->attributes = $attributes;
   }

   /**
    * @param array<string, mixed>|null $server
    */
   public static function fromGlobals(?array $server = null): self
   {
      $id = bin2hex(random_bytes(8));
      return new self($id, microtime(true), $server ?? $_SERVER, null, [], null, []);
   }

   /**
    * Get the request id.
    *
    * @return string
    */
   public function getRequestId(): string
   {
      return $this->requestId;
   }

   /**
    * Get the timestamp.
    *
    * @return float
    */
   public function getTimestamp(): float
   {
      return $this->timestamp;
   }

   /** @return array<string, mixed> */
   public function getServerParams(): array
   {
      return $this->serverParams;
   }

   /** @return array<string, mixed>|null */
   public function getAuth(): ?array
   {
      return $this->auth;
   }

   /**
    * @param array<string, mixed>|null $auth
    */
   public function withAuth(?array $auth): self
   {
      $copy = clone $this;
      $copy->auth = $auth;
      return $copy;
   }

   /** @return array<string, mixed> */
   public function getRouteMetadata(): array
   {
      return $this->routeMetadata;
   }

   /**
    * @param array<string, mixed> $routeMetadata
    */
   public function withRouteMetadata(array $routeMetadata): self
   {
      $copy = clone $this;
      $copy->routeMetadata = $routeMetadata;
      return $copy;
   }

   /**
    * Get the deadline.
    *
    * @return ?float
    */
   public function getDeadline(): ?float
   {
      return $this->deadline;
   }

   /**
    * Return a copy with the deadline.
    *
    * @param ?float $deadline
    * @return self
    */
   public function withDeadline(?float $deadline): self
   {
      $copy = clone $this;
      $copy->deadline = $deadline;
      return $copy;
   }

   /**
    * Return a copy with the attribute.
    *
    * @param string $key
    * @param mixed $value
    * @return self
    */
   public function withAttribute(string $key, mixed $value): self
   {
      $copy = clone $this;
      $copy->attributes[$key] = $value;
      return $copy;
   }

   /**
    * Return a copy with the out attribute.
    *
    * @param string $key
    * @return self
    */
   public function withoutAttribute(string $key): self
   {
      if (!array_key_exists($key, $this->attributes)) {
         return $this;
      }

      $copy = clone $this;
      unset($copy->attributes[$key]);
      return $copy;
   }

   /**
    * Get the attribute.
    *
    * @param string $key
    * @param mixed $default
    * @return mixed
    */
   public function getAttribute(string $key, mixed $default = null): mixed
   {
      return $this->attributes[$key] ?? $default;
   }

   /** @return array<string, mixed> */
   public function getAttributes(): array
   {
      return $this->attributes;
   }
}


