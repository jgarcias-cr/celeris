<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use IteratorAggregate;
use Traversable;

/**
 * Immutable, case-insensitive HTTP header map.
 *
 * `Headers` is shared by `Request` and `Response` so both sides of the pipeline use
 * the same normalization and lookup behavior. It preserves multiple values per header
 * while providing convenient single-value accessors.
 */
final class Headers implements IteratorAggregate
{
   /** @var array<string, array<int, string>> */
   private array $values;

   /**
    * @param array<string, string|array<int, string>> $headers
    */
   public function __construct(array $headers = [])
   {
      $normalized = [];
      foreach ($headers as $name => $value) {
         $key = self::normalizeName((string) $name);
         $normalized[$key] = self::normalizeValues($value);
      }

      $this->values = $normalized;
   }

   /**
    * Create from array.
    * @param array<string, string|array<int, string>> $headers
    */
   public static function fromArray(array $headers): self
   {
      return new self($headers);
   }

   /**
    * Determine whether has.
    *
    * @param string $name
    * @return bool
    */
   public function has(string $name): bool
   {
      return isset($this->values[self::normalizeName($name)]);
   }

   /**
    * Handle first.
    *
    * @param string $name
    * @param ?string $default
    * @return ?string
    */
   public function first(string $name, ?string $default = null): ?string
   {
      $values = $this->all($name);
      if ($values === []) {
         return $default;
      }

      return $values[0];
   }

   /**
    * Handle all.
    * @return array<int, string>
    */
   public function all(string $name): array
   {
      return $this->values[self::normalizeName($name)] ?? [];
   }

   /**
    * Get all headers as a multi-value array.
    *
    * @return array<string, array<int, string>>
    */
   public function toMultiValueArray(): array
   {
      return $this->values;
   }

   /**
    * Get all headers as a single-value array.
    *
    * @return array<string, string|array<int, string>>
    */
   public function toArray(): array
   {
      $result = [];
      foreach ($this->values as $name => $values) {
         $result[$name] = count($values) === 1 ? $values[0] : $values;
      }

      return $result;
   }

   /**
    * Return a copy with the given header value.
    * @param string|array<int, string> $value
    */
   public function with(string $name, string|array $value): self
   {
      $copy = clone $this;
      $copy->values[self::normalizeName($name)] = self::normalizeValues($value);
      return $copy;
   }

   /**
    * Return a copy with the given header value added.
    * If the header already exists, the new value(s) will be appended to the existing ones.
    * If the header does not exist, it will be created with the given value(s).
    * The header name is case-insensitive, so "Content-Type" and "content-type" are treated as the same header.
    * If the value is a string, it will be trimmed and added as a single value.
    * If the value is an array, each item will be trimmed and added as a separate value.
    * @param string|array<int, string> $value
    */
   public function withAdded(string $name, string|array $value): self
   {
      $copy = clone $this;
      $key = self::normalizeName($name);
      $existing = $copy->values[$key] ?? [];
      $copy->values[$key] = [...$existing, ...self::normalizeValues($value)];
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
      $key = self::normalizeName($name);
      if (!isset($this->values[$key])) {
         return $this;
      }

      $copy = clone $this;
      unset($copy->values[$key]);
      return $copy;
   }

   /**
    * Return a copy with the given header values merged.
    * If a header already exists, the new value(s) will be appended to the existing ones.
    * If a header does not exist, it will be created with the given value(s).
    * The header name is case-insensitive, so "Content-Type" and "content-type" are treated as the same header.
    * If a value is a string, it will be trimmed and added as a single value.
    * If a value is an array, each item will be trimmed and added as a separate value.
    * The input array should have header names as keys and header values as values. Header values can be either
    * strings or arrays of strings. For example: 
    * [
    *    "Content-Type" => "application/json",
    *    "Accept" => ["application/json", "text/plain"]
    * ]
    * @param array<string, string|array<int, string>> $headers
    */
   public function merge(array $headers): self
   {
      $result = $this;
      foreach ($headers as $name => $value) {
         $result = $result->with((string) $name, $value);
      }

      return $result;
   }

   /**
    * Get an iterator for the headers.
    * The iterator yields header names as keys and arrays of header values as values.
    *
    * @return Traversable<string, array<int, string>>
    */
   public function getIterator(): Traversable
   {
      yield from $this->values;
   }

   /**
    * Handle normalize name.
    *
    * @param string $name
    * @return string
    */
   private static function normalizeName(string $name): string
   {
      return strtolower(trim($name));
   }

   /**
    * Handle normalize values.
    * @param string|array<int, string> $value
    * @return array<int, string>
    */
   private static function normalizeValues(string|array $value): array
   {
      if (is_array($value)) {
         return array_values(array_map(static fn (mixed $item): string => trim((string) $item), $value));
      }

      return [trim($value)];
   }
}



