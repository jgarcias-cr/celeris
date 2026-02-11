<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Architecture;

/**
 * Purpose: implement architecture validation report behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when architecture validation report functionality is required.
 */
final class ArchitectureValidationReport
{
   /**
    * @param array<int, ArchitectureViolation> $violations
    */
   public function __construct(private array $violations = [])
   {
   }

   /**
    * Handle add.
    *
    * @param ArchitectureViolation $violation
    * @return void
    */
   public function add(ArchitectureViolation $violation): void
   {
      $this->violations[] = $violation;
   }

   /**
    * @return array<int, ArchitectureViolation>
    */
   public function violations(): array
   {
      return $this->violations;
   }

   /**
    * Determine whether is valid.
    *
    * @return bool
    */
   public function isValid(): bool
   {
      foreach ($this->violations as $violation) {
         if ($violation->severity() === 'error') {
            return false;
         }
      }

      return true;
   }

   /**
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'valid' => $this->isValid(),
         'violations' => array_map(static fn (ArchitectureViolation $violation): array => $violation->toArray(), $this->violations),
      ];
   }
}



