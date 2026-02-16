#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\AppServiceProvider;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Notification\DispatchWorker\OutboxDispatchWorker;

requireAutoload();

if (!class_exists(AppServiceProvider::class) && is_file(__DIR__ . '/../app/AppServiceProvider.php')) {
    require_once __DIR__ . '/../app/AppServiceProvider.php';
}

if (!class_exists(AppServiceProvider::class)) {
    fwrite(STDERR, "AppServiceProvider class is unavailable. Run composer install in packages/api-stub.\n");
    exit(1);
}

if (!class_exists(OutboxDispatchWorker::class)) {
    fwrite(STDERR, "OutboxDispatchWorker class is unavailable. Install celeris/notification-dispatch-worker.\n");
    exit(1);
}

try {
    [$runOnce, $maxLoops] = parseArguments($argv);

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
    registerOptionalNotificationProviders($kernel);

    $worker = $kernel->getServiceContainer()->get(OutboxDispatchWorker::class);
    if (!$worker instanceof OutboxDispatchWorker) {
        throw new RuntimeException('OutboxDispatchWorker service is not available in the container.');
    }

    $report = $runOnce ? $worker->runOnce() : $worker->runLoop($maxLoops);
    echo (string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Dispatch worker failed: ' . $exception->getMessage() . PHP_EOL);
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

/**
 * @param array<int, string> $argv
 * @return array{bool, int}
 */
function parseArguments(array $argv): array
{
    $runOnce = false;
    $maxLoops = 0;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help' || $arg === '-h') {
            printUsage();
            exit(0);
        }

        if ($arg === '--once') {
            $runOnce = true;
            continue;
        }

        if (str_starts_with($arg, '--max-loops=')) {
            $value = substr($arg, strlen('--max-loops='));
            if (!is_numeric($value) || (int) $value < 0) {
                throw new InvalidArgumentException('--max-loops must be an integer >= 0.');
            }

            $maxLoops = (int) $value;
            continue;
        }

        throw new InvalidArgumentException('Unknown argument: ' . $arg);
    }

    return [$runOnce, $maxLoops];
}

function printUsage(): void
{
    echo "Usage:\n";
    echo "  php packages/api-stub/bin/notifications-dispatch-worker.php [--once] [--max-loops=N]\n\n";
    echo "Options:\n";
    echo "  --once           Run a single dispatch pass and exit.\n";
    echo "  --max-loops=N    Run at most N loops (0 means infinite when --once is not used).\n";
}

function registerOptionalNotificationProviders(Kernel $kernel): void
{
    $providers = [
        \Celeris\Notification\Smtp\SmtpNotificationServiceProvider::class,
        \Celeris\Notification\InApp\InAppNotificationServiceProvider::class,
        \Celeris\Notification\Outbox\OutboxServiceProvider::class,
        \Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider::class,
        \Celeris\Notification\DispatchWorker\NotificationDispatchWorkerServiceProvider::class,
    ];

    foreach ($providers as $providerClass) {
        if (!class_exists($providerClass)) {
            continue;
        }

        $kernel->registerProvider(new $providerClass());
    }
}
