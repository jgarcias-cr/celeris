<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Simple contact model used by the MVC stub sample.
 *
 * This class acts as a lightweight read/write data object for view
 * rendering. In production projects you can replace or evolve it into
 * an ORM entity/value object that matches your domain rules.
 */
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
