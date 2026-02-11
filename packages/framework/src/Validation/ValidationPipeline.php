<?php

declare(strict_types=1);

namespace Celeris\Framework\Validation;

/**
 * Purpose: implement validation pipeline behavior for the Validation subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by validation components when validation pipeline functionality is required.
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



