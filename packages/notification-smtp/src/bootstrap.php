<?php

declare(strict_types=1);

// Minimal autoloader for `Celeris\\Notification\\Smtp\\` when Composer is not installed.
if (!class_exists('Celeris\\Notification\\Smtp\\SmtpNotificationChannel')) {
   spl_autoload_register(function (string $class): void {
      $prefix = 'Celeris\\Notification\\Smtp\\';
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
