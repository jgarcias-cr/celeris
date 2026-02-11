<?php

declare(strict_types=1);

namespace App\Models;

final class Contact
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
