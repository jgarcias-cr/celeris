<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return is_scalar($value) ? (string) $value : $default;
};

$envInt = static function (string $key, int $default) use ($env): int {
    $raw = $env($key);
    if ($raw === null || !is_numeric($raw)) {
        return $default;
    }

    return (int) $raw;
};

$sqlitePath = $env('DB_SQLITE_PATH', 'var/database.sqlite');
if ($sqlitePath !== null && !str_starts_with($sqlitePath, '/')) {
    $sqlitePath = dirname(__DIR__) . '/' . ltrim($sqlitePath, '/');
}

return [
    'default' => $env('DB_DEFAULT', 'main'),

    // Supported drivers: mysql, mariadb, pgsql, sqlite, sqlsrv
    // PDO extensions are required per driver (pdo_mysql, pdo_pgsql, pdo_sqlite, pdo_sqlsrv).
    'connections' => [
        'main' => [
            'driver' => 'sqlite',
            'path' => $sqlitePath,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'host' => $env('MYSQL_HOST', '127.0.0.1'),
            'port' => $envInt('MYSQL_PORT', 3306),
            'database' => $env('MYSQL_DATABASE', 'app'),
            'username' => $env('MYSQL_USERNAME', 'root'),
            'password' => $env('MYSQL_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'host' => $env('MARIADB_HOST', '127.0.0.1'),
            'port' => $envInt('MARIADB_PORT', 3306),
            'database' => $env('MARIADB_DATABASE', 'app'),
            'username' => $env('MARIADB_USERNAME', 'root'),
            'password' => $env('MARIADB_PASSWORD', ''),
            'charset' => 'utf8mb4',
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $env('PGSQL_HOST', '127.0.0.1'),
            'port' => $envInt('PGSQL_PORT', 5432),
            'database' => $env('PGSQL_DATABASE', 'app'),
            'username' => $env('PGSQL_USERNAME', 'postgres'),
            'password' => $env('PGSQL_PASSWORD', ''),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => $env('SQLSRV_HOST', '127.0.0.1'),
            'port' => $envInt('SQLSRV_PORT', 1433),
            'database' => $env('SQLSRV_DATABASE', 'app'),
            'username' => $env('SQLSRV_USERNAME', 'sa'),
            'password' => $env('SQLSRV_PASSWORD', ''),
        ],
    ],
];
