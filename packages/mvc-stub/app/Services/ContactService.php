<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Repositories\ContactRepository;
use RuntimeException;

final class ContactService
{
   public function __construct(private ContactRepository $repository) {}

   /** @return array<int, Contact> */
   public function list(): array
   {
      return $this->repository->all();
   }

   public function getOrFail(int $id): Contact
   {
      $contact = $this->repository->find($id);
      if (!$contact instanceof Contact) {
         throw new RuntimeException('Contact not found.');
      }

      return $contact;
   }
}
