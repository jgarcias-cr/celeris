<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Contact;
use App\Policies\ContactPolicy;
use Celeris\Framework\Http\FormRequest;
use Celeris\Framework\Http\RequestContext;

final class UpdateContactRequest extends FormRequest
{
   public function __construct(private ContactPolicy $policy) {}

   public function authorize(RequestContext $ctx, mixed $resource = null): bool
   {
      return $resource instanceof Contact && $this->policy->update($ctx, $resource);
   }

   /**
    * @return array<string, array<int, string>>
    */
   public function rules(): array
   {
      return [
         'first_name' => ['required', 'string', 'max:100'],
         'last_name' => ['required', 'string', 'max:100'],
         'phone' => ['nullable', 'string', 'min:7', 'max:30'],
         'address' => ['nullable', 'string', 'min:5', 'max:255'],
         'age' => ['required', 'integer', 'between:0,130'],
      ];
   }

   protected function label(string $field): string
   {
      return match ($field) {
         'first_name' => 'First name',
         'last_name' => 'Last name',
         default => parent::label($field),
      };
   }

   protected function authorizationMessage(): string
   {
      return 'You are not authorized to update this contact.';
   }
}
