<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
   require $autoload;
} else {
   require __DIR__ . '/../../framework/src/bootstrap.php';
}

use App\AppServiceProvider;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Runtime\FPMAdapter;
use Celeris\Framework\Runtime\WorkerRunner;

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
);
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

$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
