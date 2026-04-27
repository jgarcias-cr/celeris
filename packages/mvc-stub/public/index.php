<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
   require $autoload;
} else {
   $rootAutoload = __DIR__ . '/../../../vendor/autoload.php';
   if (is_file($rootAutoload)) {
      require $rootAutoload;
   } else {
      require __DIR__ . '/../../framework/src/bootstrap.php';
   }

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

use App\AppServiceProvider;
use App\Http\Controllers\ContactPageController;
use App\Http\Controllers\HomePageController;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Http\Cors\CorsPreflightMiddleware;
use Celeris\Framework\Http\Cors\CorsResponseFinalizer;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Runtime\FPMAdapter;
use Celeris\Framework\Runtime\WorkerRunner;
use Celeris\Framework\Tooling\ToolingBootstrap;

$basePath = dirname(__DIR__);
$environmentLoader = new EnvironmentLoader(
   is_file($basePath . '/.env') ? $basePath . '/.env' : null,
   is_dir($basePath . '/secrets') ? $basePath . '/secrets' : null,
   true,
   true,
);
$environmentLoader->load();

$kernel = new Kernel(
   configLoader: new ConfigLoader(
      $basePath . '/config',
      $environmentLoader,
   ),
   registerBuiltinRoutes: false,
);
$kernel->getPipeline()->add(new CorsPreflightMiddleware());
$kernel->getResponsePipeline()->add(new CorsResponseFinalizer());
$kernel->registerProvider(new AppServiceProvider());
if (class_exists(\Celeris\Notification\Smtp\SmtpNotificationServiceProvider::class)) {
   $kernel->registerProvider(new \Celeris\Notification\Smtp\SmtpNotificationServiceProvider());
}
if (class_exists(\Celeris\Notification\InApp\InAppNotificationServiceProvider::class)) {
   $kernel->registerProvider(new \Celeris\Notification\InApp\InAppNotificationServiceProvider());
}
if (class_exists(\Celeris\Notification\Outbox\OutboxServiceProvider::class)) {
   $kernel->registerProvider(new \Celeris\Notification\Outbox\OutboxServiceProvider());
}
if (class_exists(\Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider::class)) {
   $kernel->registerProvider(new \Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider());
}
if (class_exists(\Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider::class)) {
   $kernel->registerProvider(new \Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider());
}
$kernel->registerController(ContactPageController::class);
$kernel->registerController(HomePageController::class);

ToolingBootstrap::mountIfEnabled($kernel, $basePath);

$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
