<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: implement generation apply result behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when generation apply result functionality is required.
 */
final class GenerationApplyResult
{
   /**
    * @param array<int, string> $written
    * @param array<int, string> $skipped
    */
   public function __construct(
      private array $written,
      private array $skipped,
   ) {
   }

   /**
    * @return array<int, string>
    */
   public function written(): array
   {
      return $this->written;
   }

   /**
    * @return array<int, string>
    */
   public function skipped(): array
   {
      return $this->skipped;
   }

   /**
    * Determine whether has writes.
    *
    * @return bool
    */
   public function hasWrites(): bool
   {
      return $this->written !== [];
   }
}



