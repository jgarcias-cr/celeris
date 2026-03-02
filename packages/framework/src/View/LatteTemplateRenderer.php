<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

use Throwable;

/**
 * Implement latte template renderer behavior for the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class LatteTemplateRenderer implements TemplateRendererInterface
{
   public function __construct(
      private object $latteEngine,
      private string $viewsPath,
      private string $extension = 'latte',
   ) {
      if (!method_exists($this->latteEngine, 'renderToString') && !method_exists($this->latteEngine, 'render')) {
         throw ViewException::invalidRenderer(
            'latte',
            'Expected renderToString(string, array): string or render(string, array): void method.'
         );
      }
   }

   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string
   {
      $templatePath = $this->resolveTemplatePath($template);
      if (!is_file($templatePath)) {
         throw ViewException::templateNotFound($templatePath);
      }

      if (method_exists($this->latteEngine, 'renderToString')) {
         /** @var mixed $result */
         $result = $this->latteEngine->renderToString($templatePath, $data);
         return (string) $result;
      }

      ob_start();
      try {
         $this->latteEngine->render($templatePath, $data);
         return (string) ob_get_clean();
      } catch (Throwable $exception) {
         ob_end_clean();
         throw $exception;
      }
   }

   private function resolveTemplatePath(string $template): string
   {
      $basePath = rtrim($this->viewsPath, '/\\');
      $normalizedTemplate = trim(str_replace('\\', '/', $template), '/');
      $extension = trim($this->extension);
      if ($normalizedTemplate === '') {
         throw ViewException::invalidRenderer('latte', 'Template name cannot be empty.');
      }
      if ($extension !== '' && !str_ends_with($normalizedTemplate, '.' . $extension)) {
         $normalizedTemplate .= '.' . $extension;
      }

      return $basePath . '/' . $normalizedTemplate;
   }
}

