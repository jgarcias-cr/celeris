<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Implement plates template renderer behavior for the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PlatesTemplateRenderer implements TemplateRendererInterface
{
   public function __construct(
      private object $platesEngine,
      private string $extension = 'php',
   ) {
      if (!method_exists($this->platesEngine, 'render')) {
         throw ViewException::invalidRenderer('plates', 'Expected render(string, array): string method.');
      }
   }

   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string
   {
      $normalizedTemplate = trim(str_replace('\\', '/', $template), '/');
      if ($normalizedTemplate === '') {
         throw ViewException::invalidRenderer('plates', 'Template name cannot be empty.');
      }

      $extension = trim($this->extension);
      if ($extension !== '' && str_ends_with($normalizedTemplate, '.' . $extension)) {
         $normalizedTemplate = substr($normalizedTemplate, 0, -strlen('.' . $extension));
      }

      /** @var mixed $result */
      $result = $this->platesEngine->render($normalizedTemplate, $data);
      return (string) $result;
   }
}

