<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

use Celeris\Framework\Validation\Attribute\Email;
use Celeris\Framework\Validation\Attribute\InList;
use Celeris\Framework\Validation\Attribute\Length;
use Celeris\Framework\Validation\Attribute\Pattern;
use Celeris\Framework\Validation\Attribute\Range;
use Celeris\Framework\Validation\Attribute\Required;
use Celeris\Framework\Validation\Attribute\StringType;
use ReflectionClass;
use ReflectionProperty;

/**
 * Purpose: orchestrate validator engine workflows within Validation.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when validator engine functionality is required.
 */
final class ValidatorEngine
{
   /**
    * Handle validate.
    *
    * @param object $payload
    * @return ValidationResult
    */
   public function validate(object $payload): ValidationResult
   {
      $result = new ValidationResult();
      $reflection = new ReflectionClass($payload);

      foreach ($reflection->getProperties() as $property) {
         $this->validateProperty($result, $payload, $property);
      }

      return $result;
   }

   /**
    * Handle assert valid.
    *
    * @param object $payload
    * @return void
    */
   public function assertValid(object $payload): void
   {
      $result = $this->validate($payload);
      if (!$result->isValid()) {
         throw ValidationException::fromResult($result);
      }
   }

   /**
    * @param array<string, mixed> $payload
    * @param array<string, array<string, mixed>> $schema
    */
   public function validateSchema(array $payload, array $schema, bool $allowUnknown = false): ValidationResult
   {
      $result = new ValidationResult();

      foreach ($schema as $field => $rules) {
         $required = (bool) ($rules['required'] ?? false);
         $exists = array_key_exists($field, $payload);
         $value = $exists ? $payload[$field] : null;

         if ($required && (!$exists || $value === null || $value === '')) {
            $result->addError(new ValidationError($field, 'required', 'Field is required.'));
            continue;
         }
         if (!$exists) {
            continue;
         }

         $this->validateSchemaType($result, $field, $value, (string) ($rules['type'] ?? ''));
         $this->validateSchemaLength($result, $field, $value, $rules);
         $this->validateSchemaRange($result, $field, $value, $rules);
      }

      if (!$allowUnknown) {
         foreach (array_keys($payload) as $field) {
            if (!array_key_exists($field, $schema)) {
               $result->addError(new ValidationError((string) $field, 'unknown', 'Field is not declared in schema.'));
            }
         }
      }

      return $result;
   }

   /**
    * Handle validate property.
    *
    * @param ValidationResult $result
    * @param object $payload
    * @param ReflectionProperty $property
    * @return void
    */
   private function validateProperty(ValidationResult $result, object $payload, ReflectionProperty $property): void
   {
      $property->setAccessible(true);
      $path = $property->getName();
      $value = $property->isInitialized($payload) ? $property->getValue($payload) : null;

      foreach ($property->getAttributes() as $attribute) {
         $instance = $attribute->newInstance();

         if ($instance instanceof Required) {
            if ($value === null) {
               $result->addError(new ValidationError($path, 'required', 'Field is required.'));
               continue;
            }
            if (!$instance->allowEmptyString && is_string($value) && trim($value) === '') {
               $result->addError(new ValidationError($path, 'required', 'Field cannot be empty.'));
            }
            continue;
         }

         if ($value === null) {
            continue;
         }

         if ($instance instanceof StringType && !is_string($value)) {
            $result->addError(new ValidationError($path, 'string', 'Field must be a string.'));
            continue;
         }

         if ($instance instanceof Length) {
            $size = is_string($value) ? $this->stringLength($value) : (is_array($value) ? count($value) : null);
            if ($size === null) {
               $result->addError(new ValidationError($path, 'length', 'Field does not support length checks.'));
               continue;
            }
            if ($instance->min !== null && $size < $instance->min) {
               $result->addError(new ValidationError($path, 'min_length', sprintf('Minimum length is %d.', $instance->min)));
            }
            if ($instance->max !== null && $size > $instance->max) {
               $result->addError(new ValidationError($path, 'max_length', sprintf('Maximum length is %d.', $instance->max)));
            }
            continue;
         }

         if ($instance instanceof Range) {
            if (!is_numeric($value)) {
               $result->addError(new ValidationError($path, 'range', 'Field must be numeric.'));
               continue;
            }
            $number = (float) $value;
            if ($instance->min !== null && $number < $instance->min) {
               $result->addError(new ValidationError($path, 'min', sprintf('Minimum value is %s.', (string) $instance->min)));
            }
            if ($instance->max !== null && $number > $instance->max) {
               $result->addError(new ValidationError($path, 'max', sprintf('Maximum value is %s.', (string) $instance->max)));
            }
            continue;
         }

         if ($instance instanceof Email) {
            if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
               $result->addError(new ValidationError($path, 'email', 'Field must be a valid email.'));
            }
            continue;
         }

         if ($instance instanceof Pattern) {
            if (!is_string($value) || @preg_match($instance->regex, $value) !== 1) {
               $result->addError(new ValidationError($path, 'pattern', 'Field does not match required pattern.'));
            }
            continue;
         }

         if ($instance instanceof InList) {
            if (!in_array($value, $instance->values, true)) {
               $result->addError(new ValidationError($path, 'in', 'Field must be one of the allowed values.'));
            }
         }
      }
   }

   /**
    * Handle validate schema type.
    *
    * @param ValidationResult $result
    * @param string $field
    * @param mixed $value
    * @param string $type
    * @return void
    */
   private function validateSchemaType(ValidationResult $result, string $field, mixed $value, string $type): void
   {
      if ($type === '') {
         return;
      }

      $valid = match (strtolower($type)) {
         'string' => is_string($value),
         'int', 'integer' => is_int($value),
         'float', 'double', 'number' => is_float($value) || is_int($value),
         'bool', 'boolean' => is_bool($value),
         'array' => is_array($value),
         'object' => is_array($value) || is_object($value),
         default => true,
      };

      if (!$valid) {
         $result->addError(new ValidationError($field, 'type', sprintf('Expected type %s.', $type)));
      }
   }

   /**
    * @param array<string, mixed> $rules
    */
   private function validateSchemaLength(ValidationResult $result, string $field, mixed $value, array $rules): void
   {
      $minLength = isset($rules['min_length']) ? (int) $rules['min_length'] : null;
      $maxLength = isset($rules['max_length']) ? (int) $rules['max_length'] : null;
      if ($minLength === null && $maxLength === null) {
         return;
      }

      $size = is_string($value) ? $this->stringLength($value) : (is_array($value) ? count($value) : null);
      if ($size === null) {
         $result->addError(new ValidationError($field, 'length', 'Length rules apply only to strings and arrays.'));
         return;
      }

      if ($minLength !== null && $size < $minLength) {
         $result->addError(new ValidationError($field, 'min_length', sprintf('Minimum length is %d.', $minLength)));
      }
      if ($maxLength !== null && $size > $maxLength) {
         $result->addError(new ValidationError($field, 'max_length', sprintf('Maximum length is %d.', $maxLength)));
      }
   }

   /**
    * @param array<string, mixed> $rules
    */
   private function validateSchemaRange(ValidationResult $result, string $field, mixed $value, array $rules): void
   {
      $min = isset($rules['min']) ? (float) $rules['min'] : null;
      $max = isset($rules['max']) ? (float) $rules['max'] : null;
      if ($min === null && $max === null) {
         return;
      }

      if (!is_numeric($value)) {
         $result->addError(new ValidationError($field, 'range', 'Range rules apply only to numeric values.'));
         return;
      }

      $number = (float) $value;
      if ($min !== null && $number < $min) {
         $result->addError(new ValidationError($field, 'min', sprintf('Minimum value is %s.', (string) $min)));
      }
      if ($max !== null && $number > $max) {
         $result->addError(new ValidationError($field, 'max', sprintf('Maximum value is %s.', (string) $max)));
      }
   }

   /**
    * Handle string length.
    *
    * @param string $value
    * @return int
    */
   private function stringLength(string $value): int
   {
      if (function_exists('mb_strlen')) {
         return mb_strlen($value);
      }

      return strlen($value);
   }
}



