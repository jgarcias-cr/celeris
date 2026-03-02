<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Implement generated file draft behavior for the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class GeneratedFileDraft
{
   /**
    * Create a new instance.
    *
    * @param string $path
    * @param string $contents
    * @return mixed
    */
   public function __construct(
      private string $path,
      private string $contents,
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
}



