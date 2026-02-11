<?php

declare(strict_types=1);

// Minimal autoloader for `Celeris\\Framework\\` when Composer is not installed.
if (!class_exists('Celeris\\Framework\\Kernel\\Kernel')) {
   spl_autoload_register(function (string $class): void {
      $prefix = 'Celeris\\Framework\\';
      $baseDir = __DIR__ . '/';
      if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
         return;
      }
      $relative = substr($class, strlen($prefix));
      $file = $baseDir . strtr($relative, '\\', '/') . '.php';
      if (file_exists($file)) {
         require $file;
      }
   });
}
