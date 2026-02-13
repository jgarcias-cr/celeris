<?php

declare(strict_types=1);

namespace App\Http\DTOs;

/**
 * Input DTO for partial/full contact updates.
 *
 * Nullable properties indicate optional fields so callers can update
 * only the values they provide.
 */
final class UpdateContactDto
{
   public function __construct(
      public ?string $firstName = null,
      public ?string $lastName = null,
      public ?string $phone = null,
      public ?string $address = null,
      public ?int $age = null,
   ) {}
}
