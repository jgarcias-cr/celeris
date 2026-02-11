<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: define the contract for response body interface behavior in the Http subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete http services and resolved via dependency injection.
 */
interface ResponseBodyInterface
{
   /**
    * Determine whether is streaming.
    *
    * @return bool
    */
   public function isStreaming(): bool;

   /**
    * Convert to string.
    *
    * @return string
    */
   public function toString(): string;

   /**
    * @param callable(string): void $write
    */
   public function emit(callable $write): void;
}




