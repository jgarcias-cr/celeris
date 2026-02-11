<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Contact;

final class ContactRepository
{
   /** @return array<int, Contact> */
   public function all(): array
   {
      return [
         new Contact(1, 'Ada', 'Lovelace', '+1-555-0100', 'Analytical St', 36),
         new Contact(2, 'Grace', 'Hopper', '+1-555-0101', 'Compiler Ave', 37),
      ];
   }

   public function find(int $id): ?Contact
   {
      foreach ($this->all() as $contact) {
         if ($contact->id === $id) {
            return $contact;
         }
      }

      return null;
   }
}
