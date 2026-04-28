<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Implement generation apply result behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
    * Get the list of written files.
    * @return array<int, string>
    */
   public function written(): array
   {
      return $this->written;
   }

   /**
    * Get the list of skipped files.
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



