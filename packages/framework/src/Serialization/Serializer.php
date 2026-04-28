<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization;

use Celeris\Framework\Serialization\Attribute\Ignore;
use Celeris\Framework\Serialization\Attribute\SerializeName;
use JsonSerializable;
use ReflectionClass;
use UnitEnum;

/**
 * Implement serializer behavior for the Serialization subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class Serializer
{
   /**
    * Convert to json.
    *
    * @param mixed $value
    * @return string
    */
   public function toJson(mixed $value): string
   {
      $normalized = $this->normalize($value);
      return (string) json_encode(
         $normalized,
         JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
      );
   }

   /**
    * Normalize a value for serialization.
    * @return array<string, mixed>|array<int, mixed>|scalar|null
    */
   public function normalize(mixed $value): mixed
   {
      if ($value === null || is_scalar($value)) {
         return $value;
      }

      if ($value instanceof \DateTimeInterface) {
         return $value->format(DATE_ATOM);
      }

      if ($value instanceof UnitEnum) {
         return $value instanceof \BackedEnum ? $value->value : $value->name;
      }

      if ($value instanceof JsonSerializable) {
         return $this->normalize($value->jsonSerialize());
      }

      if (is_array($value)) {
         return $this->normalizeArray($value);
      }

      if (is_object($value)) {
         return $this->normalizeObject($value);
      }

      return (string) $value;
   }

   /**
    * Normalize an array for serialization.
    * @param array<mixed> $value
    * @return array<mixed>
    */
   private function normalizeArray(array $value): array
   {
      $normalized = [];
      foreach ($value as $key => $item) {
         $normalized[$key] = $this->normalize($item);
      }

      if ($this->isAssociativeArray($normalized)) {
         ksort($normalized);
      }

      return $normalized;
   }

   /**
    * Normalize an object for serialization.
    * @return array<string, mixed>
    */
   private function normalizeObject(object $value): array
   {
      $reflection = new ReflectionClass($value);
      $properties = $reflection->getProperties();
      usort($properties, static fn (\ReflectionProperty $a, \ReflectionProperty $b): int => $a->getName() <=> $b->getName());

      $serialized = [];
      foreach ($properties as $property) {
         $ignored = $property->getAttributes(Ignore::class) !== [];
         if ($ignored) {
            continue;
         }

         $property->setAccessible(true);
         if (!$property->isInitialized($value)) {
            continue;
         }

         $serializedName = $property->getName();
         $nameAttributes = $property->getAttributes(SerializeName::class);
         if ($nameAttributes !== []) {
            $instance = $nameAttributes[0]->newInstance();
            if ($instance instanceof SerializeName && trim($instance->name) !== '') {
               $serializedName = $instance->name;
            }
         }

         $serialized[$serializedName] = $this->normalize($property->getValue($value));
      }

      ksort($serialized);
      return $serialized;
   }

   /**
    * Determine if an array is associative.
    * An array is considered associative if its keys are not a continuous sequence of integers starting from 0.
    * @param array<mixed> $value
    */
   private function isAssociativeArray(array $value): bool
   {
      if ($value === []) {
         return false;
      }

      return array_keys($value) !== range(0, count($value) - 1);
   }
}



