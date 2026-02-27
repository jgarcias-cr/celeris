#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Database\Migrations\CreateContactsTableMigration;
use App\Database\Migrations\SeedContactsTableMigration;
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Config\EnvironmentLoader;
use Celeris\Framework\Database\DatabaseBootstrap;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\Migration\DatabaseMigrationRepository;
use Celeris\Framework\Database\Migration\MigrationRunner;

requireAutoload();

$basePath = dirname(__DIR__);

try {
   $action = parseAction($argv);

   $snapshot = (new ConfigLoader(
      $basePath . '/config',
      new EnvironmentLoader(
         is_file($basePath . '/.env') ? $basePath . '/.env' : null,
         is_dir($basePath . '/secrets') ? $basePath . '/secrets' : null,
         false,
         true,
      ),
   ))->snapshot();

   $config = new ConfigRepository(
      $snapshot->getItems(),
      $snapshot->getEnvironment(),
      $snapshot->getSecrets(),
      $snapshot->getFingerprint(),
      $snapshot->getLoadedAt(),
   );

   $pool = DatabaseBootstrap::poolFromConfig($config);
   $dbal = new DBAL($pool);
   $connectionName = DatabaseBootstrap::defaultConnectionName($config);
   $connection = $dbal->connection($connectionName);

   $runner = new MigrationRunner($connection, new DatabaseMigrationRepository($connection));
   $migrations = [
      new CreateContactsTableMigration(),
      new SeedContactsTableMigration(),
   ];

   if ($action === 'rollback') {
      $result = $runner->rollback($migrations, 1);
      $rolledBack = $result->rolledBack();
      if ($rolledBack === []) {
         echo "No migrations were rolled back." . PHP_EOL;
         exit(0);
      }

      echo 'Rolled back migrations: ' . implode(', ', $rolledBack) . PHP_EOL;
      exit(0);
   }

   $result = $runner->migrate($migrations);
   $applied = $result->applied();
   if ($applied === []) {
      echo "No new migrations to apply." . PHP_EOL;
      exit(0);
   }

   echo 'Applied migrations: ' . implode(', ', $applied) . PHP_EOL;
   exit(0);
} catch (Throwable $exception) {
   fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
   exit(1);
}

/**
 * @param array<int, string> $argv
 */
function parseAction(array $argv): string
{
   $action = $argv[1] ?? 'up';
   if ($action === '--help' || $action === '-h') {
      echo "Usage: php bin/migrate.php [up|rollback]" . PHP_EOL;
      exit(0);
   }

   if (!in_array($action, ['up', 'rollback'], true)) {
      throw new InvalidArgumentException('Unknown action "' . $action . '". Use "up" or "rollback".');
   }

   return $action;
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

   throw new RuntimeException('Unable to locate Composer autoload file.');
}
