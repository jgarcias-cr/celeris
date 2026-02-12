<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Purpose: implement twig template renderer behavior for the View subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by view components when twig template renderer functionality is required.
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

