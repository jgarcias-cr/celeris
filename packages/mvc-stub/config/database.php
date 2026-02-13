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

    // Supported drivers: mysql, mariadb, pgsql, sqlite, sqlsrv, firebird, ibm, oci
    // PDO extensions are required per driver:
    // - mysql/mariadb => pdo_mysql
    // - pgsql => pdo_pgsql
    // - sqlite => pdo_sqlite
    // - sqlsrv => pdo_sqlsrv
    // - firebird => pdo_firebird
    // - ibm => pdo_ibm
    // - oci => pdo_oci
    //
    // For complex setups use explicit DSN via the connection "dsn" key.
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

        'firebird' => [
            'driver' => 'firebird',
            'host' => $env('FIREBIRD_HOST', '127.0.0.1'),
            'port' => $envInt('FIREBIRD_PORT', 3050),
            'database' => $env('FIREBIRD_DATABASE', '/var/lib/firebird/data/app.fdb'),
            'username' => $env('FIREBIRD_USERNAME', 'SYSDBA'),
            'password' => $env('FIREBIRD_PASSWORD', 'masterkey'),
            'charset' => $env('FIREBIRD_CHARSET', 'UTF8'),
            'dsn' => $env('FIREBIRD_DSN'),
            'options' => [
                'id_strategy' => $env('FIREBIRD_ID_STRATEGY', 'auto'),
                'id_sequence' => $env('FIREBIRD_ID_SEQUENCE'),
                'id_sequence_pattern' => $env('FIREBIRD_ID_SEQUENCE_PATTERN'),
            ],
        ],

        'ibm' => [
            'driver' => 'ibm',
            'host' => $env('IBM_HOST', '127.0.0.1'),
            'port' => $envInt('IBM_PORT', 50000),
            'database' => $env('IBM_DATABASE', 'SAMPLE'),
            'username' => $env('IBM_USERNAME', 'db2inst1'),
            'password' => $env('IBM_PASSWORD', ''),
            'dsn' => $env('IBM_DSN'),
            'options' => [
                'protocol' => $env('IBM_PROTOCOL', 'TCPIP'),
                'id_strategy' => $env('IBM_ID_STRATEGY', 'auto'),
                'id_sequence' => $env('IBM_ID_SEQUENCE'),
                'id_sequence_pattern' => $env('IBM_ID_SEQUENCE_PATTERN'),
            ],
        ],

        'oci' => [
            'driver' => 'oci',
            'host' => $env('OCI_HOST', '127.0.0.1'),
            'port' => $envInt('OCI_PORT', 1521),
            // Defaults to service name when OCI_SERVICE_NAME is not set.
            'database' => $env('OCI_DATABASE', 'XE'),
            'username' => $env('OCI_USERNAME', 'system'),
            'password' => $env('OCI_PASSWORD', ''),
            'charset' => $env('OCI_CHARSET', 'AL32UTF8'),
            'dsn' => $env('OCI_DSN'),
            'options' => [
                'service_name' => $env('OCI_SERVICE_NAME'),
                'sid' => $env('OCI_SID'),
                'id_strategy' => $env('OCI_ID_STRATEGY', 'auto'),
                'id_sequence' => $env('OCI_ID_SEQUENCE'),
                'id_sequence_pattern' => $env('OCI_ID_SEQUENCE_PATTERN'),
            ],
        ],
    ],
];
