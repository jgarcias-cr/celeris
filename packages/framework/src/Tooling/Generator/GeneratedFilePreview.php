<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Implement generated file preview behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class GeneratedFilePreview
{
   /**
    * Create a new instance.
    *
    * @param string $path
    * @param string $contents
    * @param string $diff
    * @param bool $exists
    * @return mixed
    */
   public function __construct(
      private string $path,
      private string $contents,
      private string $diff,
      private bool $exists,
   ) {
   }

   /**
    * Handle path.
    *
    * @return string
    */
   public function path(): string
   {
      return $this->path;
   }

   /**
    * Handle contents.
    *
    * @return string
    */
   public function contents(): string
   {
      return $this->contents;
   }

   /**
    * Handle diff.
    *
    * @return string
    */
   public function diff(): string
   {
      return $this->diff;
   }

   /**
    * Handle exists.
    *
    * @return bool
    */
   public function exists(): bool
   {
      return $this->exists;
   }

   /**
    * Convert the instance to an array.
    * @return array<string, mixed>
    */
   public function toArray(): array
   {
      return [
         'path' => $this->path,
         'exists' => $this->exists,
         'diff' => $this->diff,
         'contents' => $this->contents,
      ];
   }
}



