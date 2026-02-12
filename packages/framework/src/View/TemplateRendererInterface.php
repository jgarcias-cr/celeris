<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Purpose: define the contract for template renderer behavior in the View subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete view renderers and resolved via dependency injection.
 */
interface TemplateRendererInterface
{
   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string;
}

