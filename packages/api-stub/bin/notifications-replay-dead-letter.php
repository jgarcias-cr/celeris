#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\AppServiceProvider;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Kernel\Kernel;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;

requireAutoload();

if (!class_exists(AppServiceProvider::class) && is_file(__DIR__ . '/../app/AppServiceProvider.php')) {
    require_once __DIR__ . '/../app/AppServiceProvider.php';
}

if (!class_exists(AppServiceProvider::class)) {
    fwrite(STDERR, "AppServiceProvider class is unavailable. Run composer install in packages/api-stub.\n");
    exit(1);
}

if (!interface_exists(OutboxRepositoryInterface::class) || !class_exists(OutboxMessage::class)) {
    fwrite(STDERR, "Outbox classes are unavailable. Install celeris/notification-outbox.\n");
    exit(1);
}

try {
    [$targetDeadLetterId, $limit, $dryRun] = parseArguments($argv);

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

    $container = $kernel->getServiceContainer();
    $repository = $container->get(OutboxRepositoryInterface::class);
    if (!$repository instanceof OutboxRepositoryInterface) {
        throw new RuntimeException('OutboxRepositoryInterface service is not available in the container.');
    }

    if ($container->has(ConfigRepository::class)) {
        $config = $container->get(ConfigRepository::class);
        if ($config instanceof ConfigRepository && !toBool($config->get('notifications.outbox.enabled', false))) {
            fwrite(STDERR, "Warning: notifications.outbox.enabled=false. Using in-memory repository may produce no durable replay.\n");
        }
    }

    $deadLetters = $repository->deadLetters($limit);
    $items = [];
    $replayed = 0;

    foreach ($deadLetters as $deadLetter) {
        if ($targetDeadLetterId !== '' && $deadLetter->id() !== $targetDeadLetterId) {
            continue;
        }

        $summary = [
            'dead_letter_id' => $deadLetter->id(),
            'event_name' => $deadLetter->eventName(),
            'aggregate_type' => $deadLetter->aggregateType(),
            'aggregate_id' => $deadLetter->aggregateId(),
            'attempt_count' => $deadLetter->attemptCount(),
            'status' => $deadLetter->status(),
        ];

        if ($dryRun) {
            $summary['replay_id'] = null;
            $items[] = $summary;
            continue;
        }

        $replay = OutboxMessage::create(
            eventName: $deadLetter->eventName(),
            aggregateType: $deadLetter->aggregateType(),
            aggregateId: $deadLetter->aggregateId(),
            payload: $deadLetter->payload(),
            idempotencyKey: replayIdempotencyKey($deadLetter->idempotencyKey()),
        );

        $summary['replay_id'] = $repository->enqueue($replay);
        $items[] = $summary;
        $replayed++;
    }

    echo (string) json_encode([
        'dry_run' => $dryRun,
        'target_dead_letter_id' => $targetDeadLetterId !== '' ? $targetDeadLetterId : null,
        'limit' => $limit,
        'matched' => count($items),
        'replayed' => $replayed,
        'items' => $items,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Dead-letter replay failed: ' . $exception->getMessage() . PHP_EOL);
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
 * @return array{string, int, bool}
 */
function parseArguments(array $argv): array
{
    $targetDeadLetterId = '';
    $limit = 100;
    $dryRun = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        if ($arg === '--help' || $arg === '-h') {
            printUsage();
            exit(0);
        }

        if ($arg === '--dry-run') {
            $dryRun = true;
            continue;
        }

        if (str_starts_with($arg, '--id=')) {
            $targetDeadLetterId = trim((string) substr($arg, strlen('--id=')));
            continue;
        }

        if (str_starts_with($arg, '--limit=')) {
            $value = substr($arg, strlen('--limit='));
            if (!is_numeric($value)) {
                throw new InvalidArgumentException('--limit must be numeric.');
            }

            $limit = (int) $value;
            if ($limit < 1 || $limit > 1000) {
                throw new InvalidArgumentException('--limit must be between 1 and 1000.');
            }
            continue;
        }

        throw new InvalidArgumentException('Unknown argument: ' . $arg);
    }

    return [$targetDeadLetterId, $limit, $dryRun];
}

function printUsage(): void
{
    echo "Usage:\n";
    echo "  php packages/api-stub/bin/notifications-replay-dead-letter.php [--id=ID] [--limit=N] [--dry-run]\n\n";
    echo "Options:\n";
    echo "  --id=ID      Replay only one dead-letter outbox row.\n";
    echo "  --limit=N    Read up to N dead-letter rows (1..1000, default: 100).\n";
    echo "  --dry-run    Print matched rows without enqueueing replay messages.\n";
}

function replayIdempotencyKey(string $base): string
{
    $suffix = time() . '-' . bin2hex(random_bytes(4));
    return trim($base) . ':replay:' . $suffix;
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

function toBool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        return $value !== 0;
    }

    if (is_string($value)) {
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? false;
    }

    return false;
}
