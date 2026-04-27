<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Contact;
use App\Policies\ContactPolicy;
use Celeris\Framework\Http\FormRequest;
use Celeris\Framework\Http\RequestContext;

final class EditContactRequest extends FormRequest
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
      return [];
   }

   protected function authorizationMessage(): string
   {
      return 'You are not authorized to update this contact.';
   }
}
