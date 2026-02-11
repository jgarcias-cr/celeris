<?php

declare(strict_types=1);

namespace App\Shared;

final class ViewRenderer
{
   public function __construct(private string $viewsPath) {}

   /** @param array<string, mixed> $data */
   public function render(string $view, array $data = []): string
   {
      $file = rtrim($this->viewsPath, '/\\') . '/' . ltrim($view, '/\\') . '.php';
      if (!is_file($file)) {
         throw new \RuntimeException('View not found: ' . $file);
      }

      extract($data, EXTR_SKIP);

      ob_start();
      require $file;
      return (string) ob_get_clean();
   }
}
