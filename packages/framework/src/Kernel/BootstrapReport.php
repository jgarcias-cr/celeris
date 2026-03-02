<?php

declare(strict_types=1);

namespace Celeris\Framework\Kernel;

/**
 * Implement bootstrap report behavior for the Kernel subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class BootstrapReport
{
   /**
    * @param array<int, string> $steps
    * @param array<int, string> $validators
    */
   public function __construct(
      private array $steps,
      private array $validators,
      private float $startedAt,
      private float $finishedAt,
      private int $runCount,
   ) {}

   /**
    * @return array<int, string>
    */
   public function steps(): array
   {
      return $this->steps;
   }

   /**
    * @return array<int, string>
    */
   public function validators(): array
   {
      return $this->validators;
   }

   /**
    * Handle started at.
    *
    * @return float
    */
   public function startedAt(): float
   {
      return $this->startedAt;
   }

   /**
    * Handle finished at.
    *
    * @return float
    */
   public function finishedAt(): float
   {
      return $this->finishedAt;
   }

   /**
    * Handle duration ms.
    *
    * @return float
    */
   public function durationMs(): float
   {
      return ($this->finishedAt - $this->startedAt) * 1000;
   }

   /**
    * Handle run count.
    *
    * @return int
    */
   public function runCount(): int
   {
      return $this->runCount;
   }
}




