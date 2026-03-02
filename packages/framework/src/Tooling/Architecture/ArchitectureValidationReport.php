<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Architecture;

/**
 * Implement architecture validation report behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



