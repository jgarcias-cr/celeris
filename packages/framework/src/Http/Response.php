<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: implement response behavior for the Http subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by http components when response functionality is required.
 */
final class Response
{
   private int $status;
   private Headers $headers;
   private ResponseBodyInterface $body;
   /** @var array<int, SetCookie> */
   private array $cookies;

   /**
    * @param Headers|array<string, string|array<int, string>> $headers
    * @param string|ResponseBodyInterface $body
    * @param array<int, SetCookie> $cookies
    */
   public function __construct(
      int|HttpStatus $status = 200,
      Headers|array $headers = [],
      string|ResponseBodyInterface $body = '',
      array $cookies = []
   )
   {
      $this->status = $status instanceof HttpStatus ? $status->value : $status;
      $this->headers = $headers instanceof Headers ? $headers : Headers::fromArray($headers);
      $this->body = is_string($body) ? new StringBody($body) : $body;
      $this->cookies = $cookies;
   }

   /**
    * Get the status.
    *
    * @return int
    */
   public function getStatus(): int
   {
      return $this->status;
   }

   /** @return array<string, string|array<int, string>> */
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
    * Get the body.
    *
    * @return string
    */
   public function getBody(): string
   {
      return $this->body->toString();
   }

   /**
    * Get the body object.
    *
    * @return ResponseBodyInterface
    */
   public function getBodyObject(): ResponseBodyInterface
   {
      return $this->body;
   }

   /**
    * Determine whether is streaming.
    *
    * @return bool
    */
   public function isStreaming(): bool
   {
      return $this->body->isStreaming();
   }

   /**
    * @param callable(string): void $write
    */
   public function emitBody(callable $write): void
   {
      $this->body->emit($write);
   }

   /**
    * @param string|string[] $value
    */
   public function withHeader(string $name, string|array $value): self
   {
      $copy = clone $this;
      $copy->headers = $copy->headers->with($name, $value);
      return $copy;
   }

   /**
    * @param string|string[] $value
    */
   public function withAddedHeader(string $name, string|array $value): self
   {
      $copy = clone $this;
      $copy->headers = $copy->headers->withAdded($name, $value);
      return $copy;
   }

   /**
    * Return a copy with the status.
    *
    * @param int|HttpStatus $status
    * @return self
    */
   public function withStatus(int|HttpStatus $status): self
   {
      $copy = clone $this;
      $copy->status = $status instanceof HttpStatus ? $status->value : $status;
      return $copy;
   }

   /**
    * Return a copy with the body.
    *
    * @param string|ResponseBodyInterface $body
    * @return self
    */
   public function withBody(string|ResponseBodyInterface $body): self
   {
      $copy = clone $this;
      $copy->body = is_string($body) ? new StringBody($body) : $body;
      return $copy;
   }

   /**
    * @param callable(callable(string): void): void|iterable<mixed>|resource $stream
    */
   public function withStream(mixed $stream): self
   {
      $copy = clone $this;
      if (is_callable($stream)) {
         $copy->body = new StreamBody($stream);
         return $copy;
      }
      if (is_resource($stream)) {
         $copy->body = StreamBody::fromResource($stream);
         return $copy;
      }
      if (is_iterable($stream)) {
         $copy->body = StreamBody::fromIterable($stream);
         return $copy;
      }

      throw new \InvalidArgumentException('Unsupported stream body type.');
   }

   /**
    * @return array<int, SetCookie>
    */
   public function getCookies(): array
   {
      return $this->cookies;
   }

   /**
    * Return a copy with the cookie.
    *
    * @param SetCookie $cookie
    * @return self
    */
   public function withCookie(SetCookie $cookie): self
   {
      $copy = clone $this;
      foreach ($copy->cookies as $index => $existing) {
         if ($existing->getName() === $cookie->getName()) {
            $copy->cookies[$index] = $cookie;
            return $copy;
         }
      }
      $copy->cookies[] = $cookie;
      return $copy;
   }

   /**
    * Return a copy with the out cookie.
    *
    * @param string $name
    * @return self
    */
   public function withoutCookie(string $name): self
   {
      $copy = clone $this;
      $copy->cookies = array_values(array_filter(
         $copy->cookies,
         static fn (SetCookie $cookie): bool => $cookie->getName() !== $name
      ));
      return $copy;
   }
}



