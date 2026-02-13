<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use App\Repositories\ContactRepository;
use RuntimeException;

/**
 * Application service for contact-related use cases.
 *
 * Controllers should call this service instead of reading repositories
 * directly, so business rules stay centralized and testable.
 */
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
