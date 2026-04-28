<?php

declare(strict_types=1);

namespace Celeris\Framework\Tooling\Generator;

/**
 * Define the contract for generator interface behavior in the Tooling subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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
    * Handle generation.
    *
    * @param GenerationRequest $request
    *
    * @return array<int, GeneratedFileDraft>
    */
   public function generate(GenerationRequest $request): array;
}



