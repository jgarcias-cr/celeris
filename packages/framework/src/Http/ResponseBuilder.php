<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Fluent helper for constructing immutable `Response` instances.
 *
 * It is useful in handlers where incremental composition is clearer than instantiating
 * `Response` directly with many constructor arguments.
 */
final class ResponseBuilder
{
   private int $status = 200;
   private Headers $headers;
   private ResponseBodyInterface $body;
   /** @var array<int, SetCookie> */
   private array $cookies = [];

   /**
    * Create a new instance.
    *
    * @return mixed
    */
   public function __construct()
   {
      $this->headers = new Headers();
      $this->body = new StringBody('');
   }

   /**
    * Create an instance from response.
    *
    * @param Response $response
    * @return self
    */
   public static function fromResponse(Response $response): self
   {
      $builder = new self();
      $builder->status = $response->getStatus();
      $builder->headers = $response->headers();
      $builder->body = $response->getBodyObject();
      $builder->cookies = $response->getCookies();
      return $builder;
   }

   /**
    * Handle status.
    *
    * @param int|HttpStatus $status
    * @return self
    */
   public function status(int|HttpStatus $status): self
   {
      $this->status = $status instanceof HttpStatus ? $status->value : $status;
      return $this;
   }

   /**
    * @param string|string[] $value
    */
   public function header(string $name, string|array $value): self
   {
      $this->headers = $this->headers->with($name, $value);
      return $this;
   }

   /**
    * @param string|string[] $value
    */
   public function addHeader(string $name, string|array $value): self
   {
      $this->headers = $this->headers->withAdded($name, $value);
      return $this;
   }

   /**
    * Handle cookie.
    *
    * @param SetCookie $cookie
    * @return self
    */
   public function cookie(SetCookie $cookie): self
   {
      foreach ($this->cookies as $index => $existing) {
         if ($existing->getName() === $cookie->getName()) {
            $this->cookies[$index] = $cookie;
            return $this;
         }
      }

      $this->cookies[] = $cookie;
      return $this;
   }

   /**
    * Handle body.
    *
    * @param string $body
    * @return self
    */
   public function body(string $body): self
   {
      $this->body = new StringBody($body);
      return $this;
   }

   /**
    * Handle json.
    *
    * @param array $payload
    * @param int|HttpStatus $status
    * @return self
    */
   public function json(array $payload, int|HttpStatus $status = 200): self
   {
      $this->status = $status instanceof HttpStatus ? $status->value : $status;
      $this->headers = $this->headers->with('content-type', 'application/json; charset=utf-8');
      $this->body = new StringBody((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
      return $this;
   }

   /**
    * @param callable(callable(string): void): void|iterable<mixed>|resource $stream
    */
   public function stream(mixed $stream, int|HttpStatus $status = 200): self
   {
      $this->status = $status instanceof HttpStatus ? $status->value : $status;
      if (is_callable($stream)) {
         $this->body = new StreamBody($stream);
         return $this;
      }
      if (is_resource($stream)) {
         $this->body = StreamBody::fromResource($stream);
         return $this;
      }
      if (is_iterable($stream)) {
         $this->body = StreamBody::fromIterable($stream);
         return $this;
      }

      throw new \InvalidArgumentException('Unsupported stream source.');
   }

   /**
    * Handle build.
    *
    * @return Response
    */
   public function build(): Response
   {
      return new Response(
         $this->status,
         $this->headers,
         $this->body,
         $this->cookies,
      );
   }
}


