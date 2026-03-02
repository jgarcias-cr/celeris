<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Buffered response body backed by a plain string.
 *
 * This is the default body type for most HTTP responses where full payload buffering
 * is acceptable.
 */
final class StringBody implements ResponseBodyInterface
{
   /**
    * Create a new instance.
    *
    * @param string $content
    * @return mixed
    */
   public function __construct(private string $content = '')
   {
   }

   /**
    * Determine whether is streaming.
    *
    * @return bool
    */
   public function isStreaming(): bool
   {
      return false;
   }

   /**
    * Convert to string.
    *
    * @return string
    */
   public function toString(): string
   {
      return $this->content;
   }

   /**
    * Handle emit.
    *
    * @param callable $write
    * @return void
    */
   public function emit(callable $write): void
   {
      $write($this->content);
   }
}



