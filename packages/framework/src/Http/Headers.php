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
    * @return array<int, string>
    */
   public function all(string $name): array
   {
      return $this->values[self::normalizeName($name)] ?? [];
   }

   /**
    * @return array<string, array<int, string>>
    */
   public function toMultiValueArray(): array
   {
      return $this->values;
   }

   /**
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
    * @param string|array<int, string> $value
    */
   public function with(string $name, string|array $value): self
   {
      $copy = clone $this;
      $copy->values[self::normalizeName($name)] = self::normalizeValues($value);
      return $copy;
   }

   /**
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



