<?php

declare(strict_types=1);

namespace App\Http\DTOs;

/**
 * Input DTO for contact creation requests.
 *
 * Controllers map request payload fields into this object so
 * service code receives a typed and predictable input shape.
 */
final class CreateContactDto
{
   public function __construct(
      public int $id,
      public string $firstName,
      public string $lastName,
      public string $phone,
      public string $address,
      public int $age,
   ) {}
}
