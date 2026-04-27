<?php

declare(strict_types=1);

namespace App\Http\DTOs;

use Celeris\Framework\Serialization\Attribute\Dto;
use Celeris\Framework\Serialization\Attribute\MapFrom;
use Celeris\Framework\Validation\Attribute\Length;
use Celeris\Framework\Validation\Attribute\Range;
use Celeris\Framework\Validation\Attribute\StringType;

/**
 * Input DTO for partial/full contact updates.
 *
 * Nullable properties indicate optional fields so callers can update
 * only the values they provide, while validation still applies to
 * any values that are present.
 */
#[Dto]
final class UpdateContactDto
{
   public function __construct(
      #[StringType, Length(min: 1, max: 100)]
      #[MapFrom('first_name')]
      public ?string $firstName = null,

      #[StringType, Length(min: 1, max: 100)]
      #[MapFrom('last_name')]
      public ?string $lastName = null,

      #[StringType, Length(min: 7, max: 30)]
      public ?string $phone = null,

      #[StringType, Length(min: 5, max: 255)]
      public ?string $address = null,

      #[Range(min: 0, max: 130)]
      public ?int $age = null,
   ) {}
}
