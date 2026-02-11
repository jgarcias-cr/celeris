<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Purpose: define the contract for generator interface behavior in the Tooling subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete tooling services and resolved via dependency injection.
 */
interface GeneratorInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string;

   /**
    * Handle description.
    *
    * @return string
    */
   public function description(): string;

   /**
    * @return array<int, GeneratedFileDraft>
    */
   public function generate(GenerationRequest $request): array;
}



