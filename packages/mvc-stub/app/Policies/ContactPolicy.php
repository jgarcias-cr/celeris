<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contact;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Security\Authorization\ModelPolicy;

final class ContactPolicy extends ModelPolicy
{
   public function create(RequestContext $ctx): bool
   {
      return $this->allows($ctx, null, 'contacts:write');
   }

   public function view(RequestContext $ctx, Contact $contact): bool
   {
      return $this->allows($ctx, $contact, 'contacts:read');
   }

   public function update(RequestContext $ctx, Contact $contact): bool
   {
      return $this->allows($ctx, $contact, 'contacts:write');
   }

   public function delete(RequestContext $ctx, Contact $contact): bool
   {
      return $this->allows($ctx, $contact, 'contacts:write');
   }
}
