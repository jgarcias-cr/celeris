<?php

declare(strict_types=1);

namespace Celeris\Framework\View;

/**
 * Implement php template renderer behavior for the View subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
 */
final class PhpTemplateRenderer implements TemplateRendererInterface
{
   /**
    * Create a PHP template renderer for the given views directory.
    */
   public function __construct(
      private string $viewsPath,
      private string $extension = 'php',
   ) {}

   /**
    * @param array<string, mixed> $data
    */
   public function render(string $template, array $data = []): string
   {
      $templatePath = $this->resolveTemplatePath($template);
      if (!is_file($templatePath)) {
         throw ViewException::templateNotFound($templatePath);
      }

      extract($data, EXTR_SKIP);

      ob_start();
      require $templatePath;
      return (string) ob_get_clean();
   }

   /**
    * Resolve the absolute file path for a logical template name.
    */
   private function resolveTemplatePath(string $template): string
   {
      $basePath = rtrim($this->viewsPath, '/\\');
      $normalizedTemplate = trim(str_replace('\\', '/', $template), '/');
      $extension = trim($this->extension);
      if ($normalizedTemplate === '') {
         throw ViewException::invalidRenderer('php', 'Template name cannot be empty.');
      }
      if ($extension !== '' && !str_ends_with($normalizedTemplate, '.' . $extension)) {
         $normalizedTemplate .= '.' . $extension;
      }

      return $basePath . '/' . $normalizedTemplate;
   }
}

