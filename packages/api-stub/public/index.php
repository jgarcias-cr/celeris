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
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Http\Cors\CorsPreflightMiddleware;
use Celeris\Framework\Http\Cors\CorsResponseFinalizer;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Routing\RouteMetadata;
use Celeris\Framework\Runtime\FPMAdapter;
use Celeris\Framework\Runtime\WorkerRunner;
use Celeris\Framework\Tooling\ToolingBootstrap;

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
$kernel->registerController(AuthController::class, new RouteGroup(prefix: '/api'));
$kernel->registerController(ContactController::class, new RouteGroup(prefix: '/api'));
ToolingBootstrap::mountIfEnabled($kernel, $basePath);
$kernel->routes()->get(
   '/',
   static function (ConfigRepository $config): Response {
      $frameworkVersion = 'unknown';
      $installedVersionsClass = 'Composer\\InstalledVersions';
      if (class_exists($installedVersionsClass) && $installedVersionsClass::isInstalled('celeris/framework')) {
         $frameworkVersion = $installedVersionsClass::getPrettyVersion('celeris/framework') ?? 'unknown';
      }

      $payload = [
         'name' => (string) $config->get('app.name', 'Celeris API'),
         'api_version' => (string) $config->get('app.version', '1.0.0'),
         'framework' => [
            'name' => 'celeris/framework',
            'version' => $frameworkVersion,
         ],
         'endpoints' => [
            'auth' => '/api/auth',
            'contacts' => '/api/contacts',
            'health' => '/health',
         ],
      ];

      return new Response(
         200,
         ['content-type' => 'application/json; charset=utf-8'],
         (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
      );
   },
   [],
   new RouteMetadata(name: 'api.info', summary: 'API info', tags: ['API']),
);

$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
