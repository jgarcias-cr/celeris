<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

/**
 * Implement validation pipeline behavior for the Validation subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class ValidationPipeline
{
   /** @var array<int, callable(mixed): mixed> */
   private array $steps = [];

   /**
    * @param callable(mixed): mixed $step
    */
   public function add(callable $step): void
   {
      $this->steps[] = $step;
   }

   /**
    * Handle process.
    *
    * @param mixed $payload
    * @return mixed
    */
   public function process(mixed $payload): mixed
   {
      $current = $payload;
      foreach ($this->steps as $step) {
         $current = $step($current);
      }

      return $current;
   }
}



