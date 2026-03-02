<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Define the contract for template renderer behavior in the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
interface TemplateRendererInterface
{
   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string;
}

