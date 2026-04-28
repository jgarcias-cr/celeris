<?php

declare(strict_types=1);

namespace Celeris\Framework\Serialization;

use Celeris\Framework\Serialization\Attribute\Dto;
use Celeris\Framework\Serialization\Attribute\MapFrom;
use Celeris\Framework\Validation\ValidationError;
use Celeris\Framework\Validation\ValidationException;
use Celeris\Framework\Validation\ValidationResult;
use Celeris\Framework\Validation\ValidatorEngine;
use BackedEnum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use UnitEnum;

/**
 * Implement dto mapper behavior for the Serialization subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class DtoMapper
{
   /**
    * Create a new instance.
    *
    * @param ?ValidatorEngine $validator
    * @return mixed
    */
   public function __construct(private ?ValidatorEngine $validator = null)
   {
   }

   /**
    * Determine whether supports.
    *
    * @param string $className
    * @return bool
    */
   public function supports(string $className): bool
   {
      if (!class_exists($className)) {
         return false;
      }

      $reflection = new ReflectionClass($className);
      return $reflection->getAttributes(Dto::class) !== [];
   }

   /**
    * Map payload to DTO instance.
    * @param array<string, mixed> $payload
    */
   public function map(string $className, array $payload, bool $validate = true): object
   {
      if (!$this->supports($className)) {
         throw new ValidationException(sprintf('Class "%s" is not marked as DTO.', $className));
      }

      $reflection = new ReflectionClass($className);
      $errors = new ValidationResult();
      $constructor = $reflection->getConstructor();

      $args = [];
      if ($constructor !== null) {
         foreach ($constructor->getParameters() as $parameter) {
            $field = $this->fieldNameFromParameter($parameter);
            $exists = array_key_exists($field, $payload);

            if (!$exists) {
               if ($parameter->isDefaultValueAvailable()) {
                  $args[] = $parameter->getDefaultValue();
                  continue;
               }
               if ($parameter->allowsNull()) {
                  $args[] = null;
                  continue;
               }
               $errors->addError(new ValidationError($field, 'required', 'Field is required.'));
               continue;
            }

            try {
               $args[] = $this->castValue($payload[$field], $parameter->getType(), $field);
            } catch (ValidationException $exception) {
               foreach ($exception->errors() as $row) {
                  $errors->addError(new ValidationError(
                     $row['path'] ?? $field,
                     $row['rule'] ?? 'invalid',
                     $row['message'] ?? 'Invalid value.'
                  ));
               }
            }
         }
      }

      if (!$errors->isValid()) {
         throw ValidationException::fromResult($errors);
      }

      $instance = $constructor !== null
         ? $reflection->newInstanceArgs($args)
         : $reflection->newInstance();

      foreach ($reflection->getProperties() as $property) {
         $this->mapProperty($instance, $property, $payload);
      }

      if ($validate && $this->validator !== null) {
         $this->validator->assertValid($instance);
      }

      return $instance;
   }

   /**
    * Handle map property.
    *
    * @param object $instance
    * @param ReflectionProperty $property
    * @param array<string, mixed> $payload
    */
   private function mapProperty(object $instance, ReflectionProperty $property, array $payload): void
   {
      if ($property->isStatic()) {
         return;
      }

      $field = $this->fieldNameFromProperty($property);
      if (!array_key_exists($field, $payload)) {
         return;
      }

      $property->setAccessible(true);
      if ($property->isReadOnly() && $property->isInitialized($instance)) {
         return;
      }

      $value = $this->castValue($payload[$field], $property->getType(), $field);
      $property->setValue($instance, $value);
   }

   /**
    * Handle field name from parameter.
    *
    * @param ReflectionParameter $parameter
    * @return string
    */
   private function fieldNameFromParameter(ReflectionParameter $parameter): string
   {
      $attributes = $parameter->getAttributes(MapFrom::class);
      if ($attributes !== []) {
         $instance = $attributes[0]->newInstance();
         if ($instance instanceof MapFrom && trim($instance->field) !== '') {
            return $instance->field;
         }
      }

      return $parameter->getName();
   }

   /**
    * Handle field name from property.
    *
    * @param ReflectionProperty $property
    * @return string
    */
   private function fieldNameFromProperty(ReflectionProperty $property): string
   {
      $attributes = $property->getAttributes(MapFrom::class);
      if ($attributes !== []) {
         $instance = $attributes[0]->newInstance();
         if ($instance instanceof MapFrom && trim($instance->field) !== '') {
            return $instance->field;
         }
      }

      return $property->getName();
   }

   /**
    * Handle cast value.
    *
    * @param mixed $value
    * @param ?\ReflectionType $type
    * @param string $field
    * @return mixed
    */
   private function castValue(mixed $value, ?\ReflectionType $type, string $field): mixed
   {
      if ($type === null) {
         return $value;
      }

      if (!$type instanceof ReflectionNamedType) {
         return $value;
      }

      if ($value === null) {
         if ($type->allowsNull()) {
            return null;
         }
         throw new ValidationException(
            sprintf('Field "%s" cannot be null.', $field),
            [['path' => $field, 'rule' => 'null', 'message' => 'Value cannot be null.']]
         );
      }

      if ($type->isBuiltin()) {
         return $this->castBuiltin($value, $type->getName(), $field);
      }

      $className = $type->getName();
      if (is_a($className, BackedEnum::class, true)) {
         try {
            return $className::from($value);
         } catch (\Throwable) {
            throw new ValidationException(
               sprintf('Field "%s" has invalid enum value.', $field),
               [['path' => $field, 'rule' => 'enum', 'message' => 'Invalid enum value.']]
            );
         }
      }
      if (is_a($className, UnitEnum::class, true)) {
         foreach ($className::cases() as $case) {
            if ($case->name === (string) $value) {
               return $case;
            }
         }
         throw new ValidationException(
            sprintf('Field "%s" has invalid enum case.', $field),
            [['path' => $field, 'rule' => 'enum', 'message' => 'Invalid enum case.']]
         );
      }

      if (is_a($className, \DateTimeInterface::class, true)) {
         try {
            return new \DateTimeImmutable((string) $value);
         } catch (\Throwable) {
            throw new ValidationException(
               sprintf('Field "%s" has invalid datetime.', $field),
               [['path' => $field, 'rule' => 'datetime', 'message' => 'Invalid datetime.']]
            );
         }
      }

      if (is_array($value) && $this->supports($className)) {
         /** @var array<string, mixed> $value */
         return $this->map($className, $value, true);
      }

      if (is_object($value) && $value instanceof $className) {
         return $value;
      }

      throw new ValidationException(
         sprintf('Field "%s" has invalid object type.', $field),
         [['path' => $field, 'rule' => 'type', 'message' => sprintf('Expected %s.', $className)]]
      );
   }

   /**
    * Handle cast builtin.
    *
    * @param mixed $value
    * @param string $type
    * @param string $field
    * @return mixed
    */
   private function castBuiltin(mixed $value, string $type, string $field): mixed
   {
      return match ($type) {
         'string' => is_scalar($value) ? (string) $value : $this->failType($field, $type),
         'int' => (is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1))
            ? (int) $value
            : $this->failType($field, $type),
         'float' => (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value)))
            ? (float) $value
            : $this->failType($field, $type),
         'bool' => $this->castBoolean($value, $field),
         'array' => is_array($value) ? $value : $this->failType($field, $type),
         default => $value,
      };
   }

   /**
    * Handle cast boolean.
    *
    * @param mixed $value
    * @param string $field
    * @return bool
    */
   private function castBoolean(mixed $value, string $field): bool
   {
      if (is_bool($value)) {
         return $value;
      }
      if (is_int($value)) {
         return $value !== 0;
      }
      if (is_string($value)) {
         $normalized = strtolower(trim($value));
         if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
         }
         if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
         }
      }

      $this->failType($field, 'bool');
   }

   /**
    * Handle fail type.
    *
    * @param string $field
    * @param string $type
    * @return never
    */
   private function failType(string $field, string $type): never
   {
      throw new ValidationException(
         sprintf('Field "%s" has invalid type.', $field),
         [['path' => $field, 'rule' => 'type', 'message' => sprintf('Expected %s.', $type)]]
      );
   }
}



