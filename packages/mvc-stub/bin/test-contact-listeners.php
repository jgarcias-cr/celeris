#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\AppServiceProvider;
use App\Services\ContactService;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Kernel\Kernel;

requireAutoload();
registerAppAutoload();

if (!class_exists(AppServiceProvider::class) && is_file(__DIR__ . '/../app/AppServiceProvider.php')) {
   require_once __DIR__ . '/../app/AppServiceProvider.php';
}

if (!class_exists(AppServiceProvider::class)) {
   fwrite(STDERR, "AppServiceProvider class is unavailable. Run composer install in packages/mvc-stub.\n");
   exit(1);
}

try {
   $basePath = dirname(__DIR__);
   $kernel = new Kernel(
      configLoader: new ConfigLoader(
         $basePath . '/config',
         new EnvironmentLoader(
            is_file($basePath . '/.env') ? $basePath . '/.env' : null,
            is_dir($basePath . '/secrets') ? $basePath . '/secrets' : null,
            false,
            true,
         ),
      ),
      registerBuiltinRoutes: false,
   );

   $kernel->registerProvider(new AppServiceProvider());

   $service = $kernel->getServiceContainer()->get(ContactService::class);
   if (!$service instanceof ContactService) {
      throw new RuntimeException('ContactService is not available in the container.');
   }

   $firstName = 'Test' . random_int(100, 999);
   $lastName = 'Listener' . random_int(100, 999);

   $contact = $service->create([
      'firstName' => $firstName,
      'lastName' => $lastName,
      'phone' => '+1-555-' . random_int(1000, 9999),
      'address' => random_int(100, 999) . ' Listener Test Ave',
      'age' => random_int(18, 75),
   ]);

   echo sprintf(
      "Added random contact #%d: %s %s\n",
      $contact->id,
      $contact->firstName,
      $contact->lastName,
   );
   echo "Both listener log messages should appear in var/log/app.log.\n";
} catch (Throwable $exception) {
   fwrite(STDERR, 'Listener test failed: ' . $exception->getMessage() . PHP_EOL);
   exit(1);
}

function requireAutoload(): void
{
   $candidates = [
      __DIR__ . '/../vendor/autoload.php',
      __DIR__ . '/../../../vendor/autoload.php',
   ];

   foreach ($candidates as $autoload) {
      if (is_file($autoload)) {
         require_once $autoload;
         return;
      }
   }

   $frameworkBootstrap = __DIR__ . '/../../framework/src/bootstrap.php';
   if (is_file($frameworkBootstrap)) {
      require_once $frameworkBootstrap;
      return;
   }

   fwrite(STDERR, "Unable to locate autoload/bootstrap file.\n");
   exit(1);
}

function registerAppAutoload(): void
{
   spl_autoload_register(static function (string $class): void {
      $prefix = 'App\\';
      if (!str_starts_with($class, $prefix)) {
         return;
      }

      $relative = substr($class, strlen($prefix));
      if ($relative === false) {
         return;
      }

      $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
      if (is_file($path)) {
         require $path;
      }
   });
}
