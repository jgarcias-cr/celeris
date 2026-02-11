<?php

declare(strict_types=1);

namespace Celeris\Framework\Http;

/**
 * Purpose: implement string body behavior for the Http subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by http components when string body functionality is required.
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




