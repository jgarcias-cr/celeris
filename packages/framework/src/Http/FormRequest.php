<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

use Celeris\Framework\Security\Authorization\AuthorizationException;
use Celeris\Framework\Validation\ValidationException;

/**
 * Base form-style request object shared by applications built on the framework.
 *
 * Concrete request classes typically expose `authorize()` and `rules()`, while
 * this base class provides input normalization and lightweight rule validation.
 */
abstract class FormRequest
{
   /**
    * Determine whether the current request is authorized.
    */
   abstract public function authorize(RequestContext $ctx, mixed $resource = null): bool;

   /**
    * @return array<string, array<int, string>>
    */
   abstract public function rules(): array;

   /**
    * @throws AuthorizationException
    */
   public function authorizeOrFail(RequestContext $ctx, mixed $resource = null): void
   {
      if ($this->authorize($ctx, $resource)) {
         return;
      }

      throw new AuthorizationException($this->authorizationMessage());
   }


   /**
    * Extract and normalize form values from the request. This method handles both
    * standard form submissions and raw body parsing for non-standard content types.
    * The resulting array includes all fields defined in the validation rules, ensuring
    * that missing fields are represented as empty strings.
    *
    * @return array<string, string>
    */
   public function values(Request $request): array
   {
      $body = $request->getParsedBody();
      if (!is_array($body)) {
         $raw = $request->getBody();
         if ($raw !== '') {
            $parsed = [];
            parse_str($raw, $parsed);
            if (is_array($parsed)) {
               $body = $parsed;
            }
         }
      }

      if (!is_array($body)) {
         $body = [];
      }

      $values = [];
      foreach ($body as $key => $value) {
         if (!is_string($key)) {
            continue;
         }

         $values[$key] = trim((string) $value);
      }

      foreach (array_keys($this->rules()) as $field) {
         $values[$field] = $values[$field] ?? '';
      }

      return $values;
   }
   

   /**
    * Resolve the request lifecycle for form submissions.
    *
    * Authorization runs before validation. If authorization fails, an
    * authorization exception is thrown and validation rules are not evaluated.
    *
    * @return array<string, string>
    * @throws AuthorizationException
    * @throws ValidationException
    */
   public function validateRequest(RequestContext $ctx, Request $request, mixed $resource = null): array
   {
      $this->authorizeOrFail($ctx, $resource);

      return $this->validated($this->values($request));
   }


   /**
    * Validate the given values against the rules defined in the form request. 
    * This method performs basic validation for common rules such 
    * as `required`, `string`, `integer`, `min`, `max`, and `between`. 
    * If validation fails, a `ValidationException` is thrown with details about the failed rules.
    *
    * @param array<string, string> $values
    * @return array<string, string>
    * @throws ValidationException
    */
   public function validated(array $values): array
   {
      $errors = [];
      $rules = $this->rules();

      foreach ($rules as $field => $fieldRules) {
         $value = $values[$field] ?? '';
         $isRequired = in_array('required', $fieldRules, true);

         if ($value === '') {
            if ($isRequired) {
               $errors[] = ['field' => $field, 'message' => $this->label($field) . ' is required.'];
            }
            continue;
         }

         foreach ($fieldRules as $rule) {
            if ($rule === 'required' || $rule === 'nullable' || $rule === 'string') {
               continue;
            }

            if ($rule === 'integer') {
               if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                  $errors[] = ['field' => $field, 'message' => $this->label($field) . ' must be an integer.'];
               }
               continue;
            }

            if (str_starts_with($rule, 'min:')) {
               $min = (int) substr($rule, 4);

               if (in_array('integer', $fieldRules, true)) {
                  $intValue = filter_var($value, FILTER_VALIDATE_INT);
                  if ($intValue !== false && $intValue < $min) {
                     $errors[] = ['field' => $field, 'message' => $this->label($field) . ' must be at least ' . $min . '.'];
                  }
                  continue;
               }

               if (strlen($value) < $min) {
                  $errors[] = ['field' => $field, 'message' => $this->label($field) . ' must be at least ' . $min . ' characters.'];
               }
               continue;
            }

            if (str_starts_with($rule, 'max:')) {
               $max = (int) substr($rule, 4);
               if (in_array('integer', $fieldRules, true)) {
                  $intValue = filter_var($value, FILTER_VALIDATE_INT);
                  if ($intValue !== false && $intValue > $max) {
                     $errors[] = ['field' => $field, 'message' => $this->label($field) . ' may not be greater than ' . $max . '.'];
                  }
                  continue;
               }

               if (strlen($value) > $max) {
                  $errors[] = ['field' => $field, 'message' => $this->label($field) . ' may not be greater than ' . $max . ' characters.'];
               }
               continue;
            }

            if (str_starts_with($rule, 'between:')) {
               $range = explode(',', substr($rule, 8), 2);
               $min = (int) ($range[0] ?? 0);
               $max = (int) ($range[1] ?? 0);
               $intValue = filter_var($value, FILTER_VALIDATE_INT);
               if ($intValue === false || $intValue < $min || $intValue > $max) {
                  $errors[] = ['field' => $field, 'message' => $this->label($field) . ' must be between ' . $min . ' and ' . $max . '.'];
               }
            }
         }
      }

      if ($errors !== []) {
         throw new ValidationException('Request data is invalid.', $errors);
      }

      return $values;
   }


   /**
    * By default, this method converts snake_case field names to Title Case labels.
    * For example, "first_name" becomes "First Name". Subclasses can override this
    * method to provide custom labeling logic as needed.
    *
    * @param string $field The name of the form field (e.g., "first_name").
    * @return string A human-friendly label for the field (e.g., "First Name").
    */
   protected function label(string $field): string
   {
      return ucfirst(str_replace('_', ' ', $field));
   }


   /**
    * Return the default authorization failure message for the form request.
    */
   protected function authorizationMessage(): string
   {
      return 'This action is unauthorized.';
   }
}
