<?php

declare(strict_types=1);

namespace App\Http\DTOs;

use Celeris\Framework\Serialization\Attribute\Dto;
use Celeris\Framework\Serialization\Attribute\MapFrom;
use Celeris\Framework\Validation\Attribute\Length;
use Celeris\Framework\Validation\Attribute\Range;
use Celeris\Framework\Validation\Attribute\Required;
use Celeris\Framework\Validation\Attribute\StringType;

/**
 * Input DTO for contact creation requests.
 *
 * The framework maps request payload fields into this DTO
 * and validates it before the handler is invoked.
 */
#[Dto]
final class CreateContactDto
{
   public function __construct(
      #[Required]
      public int $id,

      #[Required, StringType, Length(min: 1, max: 100)]
      #[MapFrom('first_name')]
      public string $firstName,

      #[Required, StringType, Length(min: 1, max: 100)]
      #[MapFrom('last_name')]
      public string $lastName,

      #[Required, StringType, Length(min: 7, max: 30)]
      public string $phone,

      #[Required, StringType, Length(min: 5, max: 255)]
      public string $address,

      #[Required, Range(min: 0, max: 130)]
      public int $age,
   ) {}
}
