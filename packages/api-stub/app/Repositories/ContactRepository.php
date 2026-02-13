<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Contact;

/**
 * Repository abstraction over contact persistence.
 *
 * The stub uses an in-memory array for zero-setup execution.
 * Swap this implementation for DBAL/ORM queries when connecting
 * your API to a real database.
 */
final class ContactRepository
{
   /** @var array<int, Contact> */
   private array $rows = [];

   public function __construct()
   {
      $this->rows[1] = new Contact(1, 'Ada', 'Lovelace', '+1-555-0100', 'Analytical St', 36);
   }

   /** @return array<int, Contact> */
   public function all(): array
   {
      return array_values($this->rows);
   }

   public function find(int $id): ?Contact
   {
      return $this->rows[$id] ?? null;
   }

   public function save(Contact $contact): Contact
   {
      $this->rows[$contact->id] = $contact;
      return $contact;
   }

   public function delete(int $id): bool
   {
      if (!isset($this->rows[$id])) {
         return false;
      }

      unset($this->rows[$id]);
      return true;
   }
}
