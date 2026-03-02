<?php

declare(strict_types=1);

namespace Celeris\Framework\Database\ActiveRecord\Validation;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordModel;
use Celeris\Framework\Database\ORM\EntityMetadata;

/**
 * Enforce baseline persistence constraints and model-defined validation rules for Active Record.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class MetadataConstraintValidator implements ActiveRecordValidatorInterface
{
   /**
    * Validate metadata constraints and model custom rules.
    *
    * @param ActiveRecordModel $model
    * @param EntityMetadata $metadata
    * @return ActiveRecordValidationResult
    */
   public function validate(ActiveRecordModel $model, EntityMetadata $metadata): ActiveRecordValidationResult
   {
      /** @var array<string, array<int, string>> $errors */
      $errors = [];

      foreach ($metadata->columns() as $column) {
         if ($column->isId() && $column->generated()) {
            continue;
         }

         if ($column->nullable()) {
            continue;
         }

         $value = $model->__arReadMappedValue($column->property());
         if ($value !== null && $value !== '') {
            continue;
         }

         $errors[$column->property()][] = sprintf(
            'Column "%s" cannot be null or empty.',
            $column->column()
         );
      }

      foreach ($model->validationRules() as $key => $rule) {
         if (!is_callable($rule)) {
            continue;
         }

         if (is_string($key) && $key !== '') {
            $value = $model->__arReadMappedValue($key);
            $this->appendRuleErrors($errors, $key, $rule($value, $model));
            continue;
         }

         $this->appendRuleErrors($errors, '_model', $rule($model));
      }

      if (isset($errors['_model']) && $errors['_model'] === []) {
         unset($errors['_model']);
      }

      return new ActiveRecordValidationResult($errors);
   }

   /**
    * Normalize callback output into the error map.
    *
    * @param array<string, array<int, string>> $errors
    * @param string $property
    * @param mixed $result
    * @return void
    */
   private function appendRuleErrors(array &$errors, string $property, mixed $result): void
   {
      if ($result === null || $result === true) {
         return;
      }

      if ($result === false) {
         $errors[$property][] = sprintf('Validation failed for "%s".', $property);
         return;
      }

      if (is_string($result)) {
         $message = trim($result);
         if ($message !== '') {
            $errors[$property][] = $message;
         }
         return;
      }

      if (is_array($result)) {
         foreach ($result as $message) {
            $text = trim((string) $message);
            if ($text !== '') {
               $errors[$property][] = $text;
            }
         }
         return;
      }

      $errors[$property][] = sprintf(
         'Validation rule for "%s" returned unsupported type "%s".',
         $property,
         get_debug_type($result)
      );
   }
}
