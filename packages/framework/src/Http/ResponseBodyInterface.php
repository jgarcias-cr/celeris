<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Contract for response body implementations.
 *
 * Bodies may be buffered (`StringBody`) or streaming (`StreamBody`), but both must
 * expose a common API so `Response` emission remains uniform.
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



