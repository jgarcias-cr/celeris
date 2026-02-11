<?php

declare(strict_types=1);

namespace Celeris\Framework\Kernel;

use Closure;
use RuntimeException;

/**
 * Purpose: implement bootstrap manager behavior for the Kernel subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by kernel components when bootstrap manager functionality is required.
 */
class BootstrapManager
{
   private bool $ran = false;
   private int $runCount = 0;
   /** @var array<int, array{stage: string, priority: int, index: int, name: string, callable: Closure}> */
   private array $steps = [];
   /** @var array<int, array{name: string, callable: Closure}> */
   private array $validators = [];
   private int $stepIndex = 0;
   private ?BootstrapReport $lastReport = null;

   /**
    * @param callable(self): void|callable(): void $step
    */
   public function addStep(
      callable $step,
      string $stage = 'default',
      int $priority = 0,
      ?string $name = null,
   ): void {
      $this->steps[] = [
         'stage' => trim($stage) !== '' ? trim($stage) : 'default',
         'priority' => $priority,
         'index' => $this->stepIndex++,
         'name' => $name ?? sprintf('step_%d', $this->stepIndex),
         'callable' => $step instanceof Closure ? $step : Closure::fromCallable($step),
      ];
   }

   /**
    * @param callable(self): (bool|string|array<int, string>|null) $validator
    */
   public function addValidator(callable $validator, ?string $name = null): void
   {
      $this->validators[] = [
         'name' => $name ?? sprintf('validator_%d', count($this->validators) + 1),
         'callable' => $validator instanceof Closure ? $validator : Closure::fromCallable($validator),
      ];
   }

   /**
    * Handle run.
    *
    * @return void
    */
   public function run(): void
   {
      if ($this->ran) {
         return;
      }

      $startedAt = microtime(true);
      $executedSteps = [];
      $executedValidators = [];

      $steps = $this->steps;
      usort(
         $steps,
         static fn (array $a, array $b): int
            => ($a['stage'] <=> $b['stage'])
            ?: ($a['priority'] <=> $b['priority'])
            ?: ($a['index'] <=> $b['index'])
      );

      foreach ($steps as $step) {
         $callable = $step['callable'];
         $ref = new \ReflectionFunction($callable);
         if ($ref->getNumberOfParameters() >= 1) {
            $callable($this);
         } else {
            $callable();
         }
         $executedSteps[] = sprintf('%s:%s', $step['stage'], $step['name']);
      }

      $validationErrors = [];
      foreach ($this->validators as $validator) {
         $result = $validator['callable']($this);
         $executedValidators[] = $validator['name'];

         if ($result === null || $result === true) {
            continue;
         }
         if ($result === false) {
            $validationErrors[] = sprintf('%s failed.', $validator['name']);
            continue;
         }
         if (is_string($result)) {
            $validationErrors[] = $result;
            continue;
         }
         if (is_array($result)) {
            foreach ($result as $error) {
               $validationErrors[] = (string) $error;
            }
         }
      }

      if ($validationErrors !== []) {
         throw new RuntimeException('Bootstrap validation failed: ' . implode('; ', $validationErrors));
      }

      $this->ran = true;
      $this->runCount++;
      $this->lastReport = new BootstrapReport(
         $executedSteps,
         $executedValidators,
         $startedAt,
         microtime(true),
         $this->runCount,
      );
   }

   /**
    * Handle reset.
    *
    * @return void
    */
   public function reset(): void
   {
      $this->ran = false;
   }

   /**
    * Determine whether has run.
    *
    * @return bool
    */
   public function hasRun(): bool
   {
      return $this->ran;
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

   /**
    * Handle last report.
    *
    * @return ?BootstrapReport
    */
   public function lastReport(): ?BootstrapReport
   {
      return $this->lastReport;
   }

   /**
    * @return array<int, array{stage: string, priority: int, name: string}>
    */
   public function pipeline(): array
   {
      $result = [];
      foreach ($this->steps as $step) {
         $result[] = [
            'stage' => $step['stage'],
            'priority' => $step['priority'],
            'name' => $step['name'],
         ];
      }

      usort(
         $result,
         static fn (array $a, array $b): int
            => ($a['stage'] <=> $b['stage'])
            ?: ($a['priority'] <=> $b['priority'])
            ?: ($a['name'] <=> $b['name'])
      );

      return $result;
   }
}




