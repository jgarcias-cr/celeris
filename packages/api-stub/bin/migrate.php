#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Convenience wrapper around `php celeris` migration commands.
 *
 * Usage:
 * - php bin/migrate.php up [all|MigrationFile.php] [--connection=name]
 * - php bin/migrate.php rollback [all|MigrationFile.php] [--connection=name]
 * - php bin/migrate.php fresh [--connection=name]
 * - php bin/migrate.php status [--connection=name]
 */
$action = strtolower((string) ($argv[1] ?? 'up'));
$target = (string) ($argv[2] ?? 'all');
$options = array_slice($argv, 3);

if (in_array($action, ['--help', '-h', 'help'], true)) {
   fwrite(STDOUT, "Usage: php bin/migrate.php [up|rollback|fresh|status] [all|MigrationFile.php] [--connection=name]\n");
   exit(0);
}

$mapped = match ($action) {
   'up' => ['migrate', $target === '' ? 'all' : $target],
   'rollback' => ['migrate:rollback', $target === '' ? 'all' : $target],
   'fresh' => ['migrate:fresh'],
   'status' => ['migrate:status'],
   default => null,
};

if (!is_array($mapped)) {
   fwrite(STDERR, sprintf("Unknown action \"%s\".\n", $action));
   fwrite(STDERR, "Use one of: up, rollback, fresh, status.\n");
   exit(1);
}

$cliArgs = ['celeris', ...$mapped, ...$options];
$argv = $cliArgs;
require __DIR__ . '/../celeris';
