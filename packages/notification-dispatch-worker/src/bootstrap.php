<?php

declare(strict_types=1);

// Minimal autoloader for `Celeris\\Notification\\DispatchWorker\\` when Composer is not installed.
if (!class_exists('Celeris\\Notification\\DispatchWorker\\OutboxDispatchWorker')) {
   spl_autoload_register(function (string $class): void {
      $prefix = 'Celeris\\Notification\\DispatchWorker\\';
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
