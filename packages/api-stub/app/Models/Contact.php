<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Contact entity/value object used by the API sample.
 *
 * The model includes a `toArray()` helper to normalize output for
 * JSON responses. You can adapt this class to match ORM entities or
 * richer domain objects in production.
 */
final class Contact
{
   public int $id;
   public string $firstName;
   public string $lastName;
   public string $phone;
   public string $address;
   public int $age;

   public function __construct(
      int $id,
      string $firstName,
      string $lastName,
      string $phone,
      string $address,
      int $age,
   ) {
      $this->id = $id;
      $this->firstName = $firstName;
      $this->lastName = $lastName;
      $this->phone = $phone;
      $this->address = $address;
      $this->age = $age;
   }

   /** @return array<string, int|string> */
   public function toArray(): array
   {
      return [
         'id' => $this->id,
         'first_name' => $this->firstName,
         'last_name' => $this->lastName,
         'phone' => $this->phone,
         'address' => $this->address,
         'age' => $this->age,
      ];
   }
}
