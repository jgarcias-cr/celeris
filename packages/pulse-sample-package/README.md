# Celeris Pulse Sample Package

This is a **demonstrative third-party package** for Celeris, inspired by tools like Laravel Pulse.
It is intentionally small and designed to teach package authors how to integrate with Celeris contracts.

## Installation

### Option A: Composer package (standard)

From your application root:

```bash
composer require celeris/pulse-sample-package
```

### Option B: Local path repository (this monorepo/dev workflow)

If your app consumes this package from source, add a path repository in your app `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/pulse-sample-package",
      "options": { "symlink": true }
    }
  ]
}
```

Then require it:

```bash
composer require celeris/pulse-sample-package:@dev
```

## What this sample monitors

- Request throughput and request-rate window
- Latency distribution (`avg`, `p50`, `p95`, `p99`, `max`)
- Error rate and status-code distribution
- Most-hit routes and slowest routes by average response time
- Slow requests (with route, status, request id, memory stats)
- Most active users (derived from `RequestContext::getAuth()`)
- Slow background/application tasks (manual instrumentation)
- Task failure rate

## Storage model

- Default storage is **database** (`CELERIS_PULSE_STORAGE=database`).
- At runtime, the package reads your app DB connection config and uses the selected engine (`mysql`, `mariadb`, `pgsql`, `sqlite`, `sqlsrv`).
- If enabled, it creates the metrics table automatically when missing (`CELERIS_PULSE_DB_AUTO_CREATE=true`).
- You can switch to in-memory mode (`CELERIS_PULSE_STORAGE=memory`) for local-only diagnostics.

## Table schema

Runtime table creation is implemented in `src/Monitoring/PulseTableManager.php`.

- Default table name: `celeris_pulse_measurements`
- Name validation: letters, numbers, underscore; must start with letter/underscore; max 48 chars
- Write model: append-only rows, one row per measurement event (`request` or `task`)

### Logical columns

| Column | Required | Description |
| --- | --- | --- |
| `id` | Yes | Primary key (auto increment/identity) |
| `metric_type` | Yes | `request` or `task` |
| `recorded_at_unix` | Yes | High-resolution unix timestamp used for chart bucketing |
| `recorded_at` | Yes | Database timestamp (`CURRENT_TIMESTAMP`/equivalent) |
| `request_id` | No | Request correlation id |
| `task_name` | No | Instrumented task name |
| `http_method` | No | HTTP method (for request metrics) |
| `http_path` | No | Request path |
| `route_name` | No | Route name/summary fallback |
| `http_status` | No | HTTP status code |
| `duration_ms` | Yes | Measured duration in milliseconds |
| `user_id` | No | Auth user identifier if present |
| `memory_delta_bytes` | No | Request memory delta |
| `peak_memory_bytes` | No | Peak memory in request execution |
| `success` | No | Task success flag (`1/0` or boolean by engine) |
| `tags_json` | No | JSON payload for task tags |
| `app_env` | No | App environment snapshot (for filtering) |
| `connection_name` | No | DB connection used to persist metric |

### Column usage by metric type

| Column | `request` rows | `task` rows |
| --- | --- | --- |
| `metric_type` | `request` | `task` |
| `request_id` | populated | `NULL` |
| `task_name` | `NULL` | populated |
| `http_method`, `http_path`, `route_name`, `http_status` | populated | `NULL` |
| `memory_delta_bytes`, `peak_memory_bytes`, `user_id` | populated when available | `NULL` |
| `success` | `NULL` | populated |
| `tags_json` | `NULL` | populated |

### Indexes

The package creates these indexes for dashboard and chart queries:

- `idx_*_recorded_time` on (`recorded_at_unix`)
- `idx_*_metric_time` on (`metric_type`, `recorded_at_unix`)
- `idx_*_route_time` on (`route_name`, `recorded_at_unix`)
- `idx_*_user_time` on (`user_id`, `recorded_at_unix`)
- `idx_*_status_time` on (`http_status`, `recorded_at_unix`)
- `idx_*_task_time` on (`task_name`, `recorded_at_unix`)

`*` is replaced by the table name prefix used by the runtime DDL builder.

### Engine-specific data types

| Logical field | SQLite | MySQL/MariaDB | PostgreSQL | SQL Server |
| --- | --- | --- | --- | --- |
| `id` | `INTEGER PRIMARY KEY AUTOINCREMENT` | `BIGINT UNSIGNED AUTO_INCREMENT` | `BIGSERIAL` | `BIGINT IDENTITY(1,1)` |
| `recorded_at_unix`, `duration_ms` | `REAL` | `DOUBLE` | `DOUBLE PRECISION` | `FLOAT` |
| `recorded_at` | `TEXT` default `datetime('now')` | `DATETIME(6)` | `TIMESTAMPTZ` | `DATETIME2(6)` |
| text/varchar fields | `TEXT` | `VARCHAR(...)` / `LONGTEXT` | `VARCHAR(...)` / `JSONB` | `NVARCHAR(...)` / `NVARCHAR(MAX)` |
| `success` | `INTEGER` | `TINYINT(1)` | `SMALLINT` | `BIT` |

## What else you can monitor next

- Database query count and query-time budget per request
- Slow query fingerprints (SQL hash + bindings class)
- Cache hit/miss ratio by store and key namespace
- External API latency/error rates by provider
- Queue wait time, processing time, retries, and dead letters
- Lock contention and transaction retry counts
- Memory growth trend across worker requests
- Endpoint-level Apdex and SLO burn-rate alerts
- Per-tenant/per-module usage and performance segmentation

## Package structure

```text
packages/pulse-sample-package/
  .env.example
  composer.json
  config/
    celeris_pulse.php
  src/
    PulseServiceProvider.php
    Config/PulseSettings.php
    Contracts/
      MetricStoreInterface.php
      PulseRecorderInterface.php
    Monitoring/
      DatabaseMetricStore.php
      MetricAggregator.php
      PulseTableManager.php
      RequestMetric.php
      TaskMetric.php
      InMemoryMetricStore.php
      PulseRecorder.php
    Http/
      Middleware/
        RequestMetricsMiddleware.php
        PulseAccessMiddleware.php
      Controllers/
        PulseController.php
    Routes/PulseRoutes.php
```

## Integration in a Celeris app

### 1. Register provider

```php
use Celeris\Sample\Pulse\PulseServiceProvider;

$kernel->registerProvider(new PulseServiceProvider());
```

### 2. Add request profiling middleware globally

```php
use Celeris\Sample\Pulse\Http\Middleware\RequestMetricsMiddleware;

$kernel->addGlobalMiddleware(RequestMetricsMiddleware::class);
```

### 3. Register package routes

```php
use Celeris\Sample\Pulse\Routes\PulseRoutes;

PulseRoutes::register($kernel->routes()); // default prefix: /_pulse
```

If you want another prefix:

```php
PulseRoutes::register($kernel->routes(), '/_ops/pulse');
```

### 4. Add config file in your app

Copy `config/celeris_pulse.php` into your application `config/` directory.

No manual migration is required when `CELERIS_PULSE_DB_AUTO_CREATE=true`; the package creates the metrics table/indexes at runtime.

### 5. Add Pulse env keys to your app `.env`

Use `packages/pulse-sample-package/.env.example` as the source of truth.

Suggested `.env` values:

```dotenv
CELERIS_PULSE_ENABLED=true
CELERIS_PULSE_ENVIRONMENTS=development,local
CELERIS_PULSE_TOKEN=
CELERIS_PULSE_STORAGE=database
CELERIS_PULSE_DB_CONNECTION=
CELERIS_PULSE_DB_TABLE=celeris_pulse_measurements
CELERIS_PULSE_DB_AUTO_CREATE=true
CELERIS_PULSE_ROUTE_PREFIX=/_pulse
CELERIS_PULSE_IGNORE_PATHS=/_pulse,/health
CELERIS_PULSE_MAX_REQUEST_SAMPLES=2000
CELERIS_PULSE_MAX_TASK_SAMPLES=1000
CELERIS_PULSE_SLOW_REQUEST_MS=300
CELERIS_PULSE_SLOW_TASK_MS=200
CELERIS_PULSE_DASHBOARD_LIMIT=20
```

## Available endpoints

- `GET /_pulse/summary`
- `GET /_pulse/requests/slow`
- `GET /_pulse/users/active`
- `GET /_pulse/tasks/slow`

Optional query params:

- `limit`
- `slow_request_ms`
- `slow_task_ms`

The endpoints aggregate from persisted measurements when storage is `database`.

When `CELERIS_PULSE_TOKEN` is set, send:

`x-celeris-pulse-token: <token>`

## Manual task instrumentation example

Use `PulseRecorderInterface` to measure expensive operations (imports, report generation, external API syncs):

```php
use Celeris\Sample\Pulse\Contracts\PulseRecorderInterface;

final class BillingSyncService
{
    public function __construct(private PulseRecorderInterface $pulse)
    {
    }

    public function syncMonth(string $month): void
    {
        $this->pulse->measure(
            name: 'billing.sync.month',
            task: fn () => $this->runSync($month),
            tags: ['module' => 'billing', 'month' => $month],
        );
    }

    private function runSync(string $month): void
    {
        // Expensive work here.
    }
}
```

## Why this is a good package template

- Uses framework-native contracts (`Request`, `Response`, middleware, providers)
- Keeps package internals decoupled behind small interfaces
- Demonstrates environment/toggle gating and optional token guard
- Shows how to expose package routes without kernel hacks
- Makes extension points explicit (swap `MetricStoreInterface` with Redis/SQL/OpenTelemetry)
