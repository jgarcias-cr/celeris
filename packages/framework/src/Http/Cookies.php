<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Immutable cookie collection for request-side cookie values.
 *
 * The class normalizes and exposes cookie name/value pairs with predictable lookup
 * semantics. It represents incoming cookies only; outgoing `Set-Cookie` headers are
 * modeled separately by `SetCookie`.
 */
final class Cookies
{
   /** @var array<string, string> */
   private array $values;

   /**
    * Initialize with the given cookie values.
    * @param array<string, string> $values
    */
   public function __construct(array $values = [])
   {
      $normalized = [];
      foreach ($values as $name => $value) {
         $normalized[(string) $name] = (string) $value;
      }

      $this->values = $normalized;
   }


   /**
    * Create an instance from array.
    * @param array<string, string> $values
    */
   public static function fromArray(array $values): self
   {
      return new self($values);
   }


   /**
    * Create an instance from cookie header.
    *
    * @param ?string $header
    * @return self
    */
   public static function fromCookieHeader(?string $header): self
   {
      if ($header === null || trim($header) === '') {
         return new self();
      }

      $cookies = [];
      foreach (explode(';', $header) as $item) {
         $pair = explode('=', trim($item), 2);
         if ($pair[0] === '') {
            continue;
         }
         $cookies[$pair[0]] = urldecode($pair[1] ?? '');
      }

      return new self($cookies);
   }


   /**
    * Determine whether has the value.
    *
    * @param string $name
    * @return bool
    */
   public function has(string $name): bool
   {
      return array_key_exists($name, $this->values);
   }


   /**
    * Get the value.
    *
    * @param string $name
    * @param ?string $default
    * @return ?string
    */
   public function get(string $name, ?string $default = null): ?string
   {
      return $this->values[$name] ?? $default;
   }


   /**
    * Get all cookie values.
    * @return array<string, string>
    */
   public function all(): array
   {
      return $this->values;
   }


   /**
    * Return a copy with the value.
    *
    * @param string $name
    * @param string $value
    * @return self
    */
   public function with(string $name, string $value): self
   {
      $copy = clone $this;
      $copy->values[$name] = $value;
      return $copy;
   }
   

   /**
    * Return a copy with the out.
    *
    * @param string $name
    * @return self
    */
   public function without(string $name): self
   {
      if (!$this->has($name)) {
         return $this;
      }

      $copy = clone $this;
      unset($copy->values[$name]);
      return $copy;
   }
}



