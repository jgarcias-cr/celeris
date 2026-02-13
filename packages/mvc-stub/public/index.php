<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
   require $autoload;
} else {
   require __DIR__ . '/../../framework/src/bootstrap.php';
}

use App\AppServiceProvider;
use App\Http\Controllers\ContactPageController;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Kernel\Kernel;
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
$kernel->registerController(ContactPageController::class);

$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
