<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Implement twig template renderer behavior for the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class TwigTemplateRenderer implements TemplateRendererInterface
{
   public function __construct(
      private object $twigEnvironment,
      private string $extension = 'twig',
   ) {
      if (!method_exists($this->twigEnvironment, 'render')) {
         throw ViewException::invalidRenderer('twig', 'Expected render(string, array): string method.');
      }
   }

   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string
   {
      $normalizedTemplate = trim(str_replace('\\', '/', $template), '/');
      if ($normalizedTemplate === '') {
         throw ViewException::invalidRenderer('twig', 'Template name cannot be empty.');
      }

      $extension = trim($this->extension);
      if ($extension !== '' && !str_ends_with($normalizedTemplate, '.' . $extension)) {
         $normalizedTemplate .= '.' . $extension;
      }

      /** @var mixed $result */
      $result = $this->twigEnvironment->render($normalizedTemplate, $data);
      return (string) $result;
   }
}

