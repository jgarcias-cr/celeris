<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: implement generated file draft behavior for the Tooling subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by tooling components when generated file draft functionality is required.
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



