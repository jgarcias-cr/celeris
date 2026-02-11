# Celeris Framework User Manual

## Index

1. [1. Core Concepts](#1-core-concepts)
2. [1.1 Detailed Request Lifecycle](#11-detailed-request-lifecycle)
3. [1.2 Worker Mode: General Introduction](#12-worker-mode-general-introduction)
4. [1.3 How `WorkerRunner` Works](#13-how-workerrunner-works)
5. [1.4 How `FPMAdapter` Works (and how it differs from worker adapters)](#14-how-fpmadapter-works-and-how-it-differs-from-worker-adapters)
6. [1.5 Canonical Worker Mode Lifecycle](#15-canonical-worker-mode-lifecycle)
7. [2. Minimal Bootstraps](#2-minimal-bootstraps)
8. [3. Configuration, Environment, and Bootstrap](#3-configuration-environment-and-bootstrap)
9. [4. DI Container and Service Providers](#4-di-container-and-service-providers)
10. [5. HTTP Abstractions](#5-http-abstractions)
11. [6. Routing, Controllers, and Middleware](#6-routing-controllers-and-middleware)
12. [7. API Project: End-to-End Example](#7-api-project-end-to-end-example)
13. [8. MVC Project: End-to-End Example](#8-mvc-project-end-to-end-example)
14. [9. Database and ORM](#9-database-and-orm)
15. [10. Optional Active Record Compatibility Layer](#10-optional-active-record-compatibility-layer)
16. [11. Security Subsystem](#11-security-subsystem)
17. [12. Validation and Serialization](#12-validation-and-serialization)
18. [13. Caching and HTTP Cache Semantics](#13-caching-and-http-cache-semantics)
19. [14. Domain Events](#14-domain-events)
20. [15. Tooling Platform (CLI + Web)](#15-tooling-platform-cli--web)
21. [16. Distributed Features (Microservice/SOA)](#16-distributed-features-microservicesoa)
22. [17. Transactions, CRUD, and Connection Patterns (Decision Guide)](#17-transactions-crud-and-connection-patterns-decision-guide)
23. [18. API vs MVC Cheat Sheet](#18-api-vs-mvc-cheat-sheet)
24. [19. Operational Checklist](#19-operational-checklist)
25. [20. Reference Map](#20-reference-map)

This manual documents the current framework implementation in `packages/framework/src`.

It is written as a practical guide with two complete project styles:
- API-first project
- MVC-style project (controllers + PHP views)

It also covers all core subsystems: runtime, kernel lifecycle, DI, routing, middleware, HTTP abstractions, configuration, security, validation/serialization, database/ORM, optional Active Record compatibility, caching, tooling, and distributed services.

## 1. Core Concepts

Celeris is built around these rules:
- One process can handle many requests (worker-safe)
- Bootstrap once, reset deterministically between requests
- No hidden globals for request state (`RequestContext` is explicit)
- Request objects are immutable; response objects are immutable with builder support
- DI and service providers are explicit
- Routing and middleware order are deterministic

Key runtime flow:
1. `WorkerRunner` obtains `RuntimeRequest` from an adapter (`FPMAdapter`, `RoadRunnerAdapter`, `SwooleAdapter`)
2. `Kernel::handle(RequestContext, Request)` executes security pre-checks, routing, middleware, handler invocation, and response finalizers
3. `Kernel::reset()` clears request-scoped state and applies hot-reload snapshot logic when enabled
4. Worker adapter resets runtime-level state

### 1.1 Detailed Request Lifecycle

This is the full request lifecycle as implemented by `WorkerRunner + Kernel`.

```text
+---------------------------+
| Runtime Adapter           |
| (FPM/RoadRunner/Swoole)   |
+------------+--------------+
             |
             v
+---------------------------+
| WorkerRunner::run()       |
| - kernel.boot() once      |
| - adapter.start() once    |
+------------+--------------+
             |
             v (loop)
+---------------------------+
| adapter.nextRequest()     |
| -> RuntimeRequest         |
+------------+--------------+
             |
             v
+---------------------------+
| Kernel::handle(ctx, req)  |
+------------+--------------+
             |
             v
+---------------------------+
| Request scope setup       |
| - create request container|
| - ctx + container         |
| - contextContainer.enter  |
+------------+--------------+
             |
             v
+---------------------------+
| Security pre-routing      |
| - normalize input         |
| - request validator       |
| - SQL injection guard     |
| - rate limiter            |
| - authenticate            |
| - CSRF (contextual)       |
+------------+--------------+
             |
             v
+---------------------------+
| Router resolve            |
| - method/path/version     |
+------+--------------------+
       | hit
       |                     miss
       |                     |
       v                     v
+---------------------+   +----------------------+
| Route metadata set  |   | 405 or 404 response  |
| authorize handler   |   +----------+-----------+
+----------+----------+              |
           |                         |
           v                         v
+---------------------------+   +---------------------------+
| Middleware dispatcher     |   | Response pipeline         |
| (global + route ordered)  |   | finalizers                |
+------------+--------------+   +------------+--------------+
             |                               |
             v                               v
+---------------------------+      +------------------------+
| Handler invocation        |      | return response        |
| - args resolved from:     |      +------------------------+
|   RequestContext/Request  |
|   container services      |
|   DTO mapper              |
|   path params             |
+------------+--------------+
             |
             v
+---------------------------+
| Raw handler result        |
| -> Response/JSON/text     |
+------------+--------------+
             |
             v
+---------------------------+
| Response pipeline         |
| - security headers        |
| - HTTP cache headers      |
+------------+--------------+
             |
             v
+---------------------------+
| WorkerRunner sends output |
| adapter.send(...)         |
+------------+--------------+
             |
             v
+---------------------------+
| Deterministic cleanup     |
| - kernel.reset()          |
| - adapter.reset()         |
+------------+--------------+
             |
             v
        next request
```

Execution details by stage:
1. Process start.
- `WorkerRunner` calls `kernel.boot()` once, then `adapter.start()`.
- Bootstrap pipeline executes and container is built.

2. Request frame acquisition.
- Adapter returns a `RuntimeRequest` containing `RequestContext + Request`.
- In FPM mode this is one request per process lifecycle.
- In worker mode this repeats in a loop for many requests.

3. Request scope initialization.
- Kernel creates a request-scoped container from root container.
- Scope is attached to `RequestContext` attribute `container`.
- Context is entered into `RequestContextContainer` (fiber-safe stack).

4. Pre-routing security gate.
- `SecurityKernelGuard::beforeRouting()` runs:
- Input normalization.
- Request shape/size/header validation.
- SQLi pattern guard for query/body scalar inputs.
- Rate limit enforcement.
- Authentication strategy resolution.
- CSRF protection only when context indicates session-style semantics.

5. Route resolution and authorization.
- Router resolves by HTTP method + path + API version (`x-api-version` or `api_version` query).
- If no route but path matches other methods, kernel returns `405` with `Allow`.
- If no route match at all, kernel returns `404`.
- On match, route metadata and path params are attached to context.
- Policy engine evaluates `#[Authorize]` rules for class/method handler.

6. Middleware pipeline execution.
- Kernel pipeline middleware executes first.
- Middleware dispatcher executes registered global middleware, then route middleware, in deterministic order.
- Middleware can short-circuit by returning a response early.

7. Handler invocation and argument binding.
- Handler is resolved from callable, `[Class, method]`, or `Class@method`.
- Class handlers are resolved from request container when available.
- Parameters are bound from:
- `RequestContext`, `Request`.
- Container services by type.
- DTO mapping for classes annotated with `#[Dto]`.
- Path parameters by name and type casting (`int`, `float`, `bool`, `string`).

8. Response materialization.
- If handler returns `Response`, it is used directly.
- If handler returns `array/object`, kernel serializes to JSON response.
- Otherwise kernel casts to text response body.

9. Response finalization.
- `ResponsePipeline` finalizers run in order.
- Security headers finalizer applies security defaults.
- HTTP cache finalizer can add `Cache-Control`, `Vary`, `ETag`, `Last-Modified`.

10. Error paths.
- `ValidationException` becomes structured JSON error response.
- `SecurityException` becomes response with status + auth/security headers.
- Unhandled exceptions in `WorkerRunner` are converted to `500 Internal Server Error`.

11. Cleanup and reset.
- Kernel always leaves/clears current request context stack.
- Request-scoped container is cleared.
- `kernel.reset()` runs request cleanup hooks and context cleanup.
- If hot reload is enabled and config fingerprint changed, container/services are rebuilt safely.
- Adapter reset runs to clear runtime-specific request artifacts.

12. Process shutdown.
- When adapter has no more requests, runner calls `adapter.stop()` and `kernel.shutdown()`.
- Registered shutdown hooks execute deterministically.

### 1.2 Worker Mode: General Introduction

Worker Mode means one long-lived PHP process handles many HTTP requests in sequence.

Core characteristics:
- Bootstrap is executed once per process startup.
- Services can stay in memory between requests.
- Each request still needs explicit isolation and cleanup.
- Request-scoped state must be reset deterministically after every request.

Why Worker Mode exists:
- Avoid repeated framework bootstrap cost on every request.
- Improve throughput/latency for hot paths.
- Enable long-lived infrastructure in memory (connection pools, caches, routing trees).

What you must handle carefully:
- Never keep request/user-specific mutable state in singleton services.
- Always rely on request scope (`RequestContext`, request-scoped container services).
- Ensure reset hooks clear transient runtime artifacts between requests.

In this framework, Worker Mode safety is enforced by:
- `RequestContextContainer` with explicit enter/leave semantics (fiber-safe isolation).
- request-scoped container creation in `Kernel::handle()`.
- deterministic cleanup in `Kernel::reset()` and adapter `reset()`.

### 1.3 How `WorkerRunner` Works

`WorkerRunner` is the process-level orchestration loop. It sits between the runtime adapter and the kernel.

Execution contract:
1. Start phase (once).
- `kernel.boot()` executes bootstrap/config/container initialization.
- `adapter.start()` initializes runtime transport.

2. Request loop.
- `adapter.nextRequest()` returns `RuntimeRequest` frames until it returns `null`.
- For each frame, `WorkerRunner` calls `kernel.handle(ctx, req)`.
- `SecurityException` is converted into a response with its status/headers.
- Any other unhandled exception is converted to `500 Internal Server Error`.

3. Emit and cleanup per request.
- `adapter.send(runtimeRequest, response)` writes response back to runtime transport.
- In a `finally` block, `WorkerRunner` always executes:
- `kernel.reset()`
- `adapter.reset()`
- This guarantees deterministic cleanup even when handler execution fails.

4. Stop phase (once).
- When no more request frames are available, `adapter.stop()` is called.
- `kernel.shutdown()` is called and shutdown hooks run.

Practical consequence:
- `WorkerRunner` is the boundary that guarantees one bootstrap/many requests while preserving per-request isolation.

### 1.4 How `FPMAdapter` Works (and how it differs from worker adapters)

`FPMAdapter` is the bridge for traditional PHP-FPM execution.

What PHP-FPM is (conceptually):
- `FPM` stands for `FastCGI Process Manager` for PHP.
- A web server (typically Nginx, sometimes Apache via proxy) receives HTTP requests and forwards dynamic PHP requests to PHP-FPM using the FastCGI protocol.
- PHP-FPM maintains a pool of PHP worker processes (`pm = static|dynamic|ondemand`) and distributes incoming requests among them.

How FPM request processing works:
1. Web server accepts HTTP request.
2. Web server routes PHP target (for example `public/index.php`) to PHP-FPM.
3. One FPM worker process executes the script for that request.
4. Script returns output/status/headers through FastCGI back to web server.
5. Worker becomes available for another request.

Important implications:
- FPM worker processes are long-lived, but PHP userland execution context is request-bounded.
- Your application code is loaded/executed per request invocation of the front controller.
- Request-specific runtime state does not carry over automatically between requests in normal FPM flow.
- Shared process-level resources can still exist (for example OPcache bytecode cache), but request data itself must be treated as ephemeral.

How this differs from explicit worker runtimes:
- In RoadRunner/Swoole worker mode, one application bootstrap can intentionally serve many requests in a single userland runtime loop.
- In FPM mode, the web server/FPM lifecycle already provides request boundaries, so the framework adapter exposes one request frame and exits.

Behavior in this framework:
- `start()` is a no-op.
- `nextRequest()` serves exactly one request using:
- `RequestContext::fromGlobals($_SERVER)`
- `Request::fromGlobals(...)`
- It then returns `null` on the next call (single runtime frame).
- `send()` emits status, headers, cookies, and body using PHP output functions.
- `reset()` and `stop()` are no-ops.

Why this design:
- In FPM, each script invocation is already request-bounded by the web server/FPM model.
- So the adapter exposes one request frame to `WorkerRunner`, then exits cleanly.

Contrast with worker adapters:
- `RoadRunnerAdapter` and `SwooleAdapter` are loop-driven and can yield many request frames in one process.
- Their `reset()` stage is operationally important because process memory is reused across many requests.

### 1.5 Canonical Worker Mode Lifecycle

This section describes the canonical lifecycle of a worker-mode process, independent of a specific transport implementation.

Canonical lifecycle phases:
1. Process startup.
- Worker process starts.
- Runtime adapter initializes transport/runtime bindings.

2. Bootstrap once.
- Kernel bootstrap runs once for the process.
- Core services/container/routing/security/config are initialized.

3. Request loop.
- Worker waits for request frame.
- If frame exists, request is handled.
- If no frame (shutdown signal/transport closed), loop exits.

4. Per-request execution.
- Request context/scope setup.
- Security gate, routing, middleware, handler, finalizers.
- Response emitted to transport.

5. Deterministic reset.
- Request-scoped memory and runtime artifacts are cleared.
- Optional hot-reload checks can rebuild container safely.

6. Graceful shutdown.
- Adapter stop and kernel shutdown hooks run.
- Process exits.

Graph 1: Canonical worker state flow

```text
+-------------------+
| Process Started   |
+---------+---------+
          |
          v
+-------------------+
| Adapter Start     |
| Kernel Boot Once  |
+---------+---------+
          |
          v
+---------------------------+
| Wait For Next Request     |
| adapter.nextRequest()     |
+----+------------------+---+
     |                  |
     | request frame    | null / stop signal
     v                  v
+-------------------+  +--------------------+
| Handle Request    |  | Exit Loop          |
| kernel.handle()   |  +---------+----------+
+---------+---------+            |
          |                      v
          v            +--------------------+
+-------------------+  | Adapter Stop       |
| Send Response     |  | Kernel Shutdown    |
| adapter.send()    |  +---------+----------+
+---------+---------+            |
          |                      v
          v            +--------------------+
+-------------------+  | Process Ended      |
| Reset Per Request |  +--------------------+
| kernel.reset()    |
| adapter.reset()   |
+---------+---------+
          |
          +-------> back to "Wait For Next Request"
```

Graph 2: Canonical per-request sequence in worker mode

```text
Runtime Adapter      WorkerRunner          Kernel              App Code
     |                   |                   |                    |
     | nextRequest()     |                   |                    |
     |------------------>|                   |                    |
     |   RuntimeRequest  |                   |                    |
     |<------------------|                   |                    |
     |                   | handle(ctx, req)  |                    |
     |                   |------------------>|                    |
     |                   |                   | pipeline + handler |
     |                   |                   |------------------->|
     |                   |                   |<-------------------|
     |                   |    Response       |                    |
     |                   |<------------------|                    |
     | send(response)    |                   |                    |
     |<------------------|                   |                    |
     |                   | reset()           |                    |
     |                   |------------------>|                    |
     |                   | adapter.reset()   |                    |
     |                   |-------------------+------------------> |
```

Why this canonical model matters:
- It makes bootstrap cost predictable (`once per process`).
- It preserves correctness via strict per-request reset.
- It allows high throughput while avoiding cross-request state leaks.

Common mistakes to avoid in worker mode:
- Storing request/user mutable state in singleton services.
- Assuming static properties are request-isolated.
- Forgetting cleanup in custom middleware/adapters.
- Performing non-idempotent side effects before request validation/authorization gates.

## 2. Minimal Bootstraps

### 2.1 Minimal API/MVC front controller

`public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Celeris\Framework\Kernel\Kernel;
use Celeris\Framework\Runtime\FPMAdapter;
use Celeris\Framework\Runtime\WorkerRunner;

$kernel = new Kernel();
$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
```

### 2.2 Worker runtimes (RoadRunner/Swoole)

`RoadRunnerAdapter` and `SwooleAdapter` use callback-based transport integration. You provide receiver/responder callbacks and run through `WorkerRunner`.

```php
$adapter = new RoadRunnerAdapter(
    receiver: fn (): ?array => $nextFrameFromRuntime(),
    responder: fn ($runtimeRequest, $response): void => $sendBackToRuntime($runtimeRequest, $response),
);

$runner = new WorkerRunner($kernel, $adapter);
$runner->run();
```

## 3. Configuration, Environment, and Bootstrap

By default, `Kernel` loads configuration from:
- `config/*.php`
- `.env`
- `secrets/` directory

through `ConfigLoader + EnvironmentLoader`, then builds immutable runtime config snapshots (`ConfigSnapshot`).

### 3.1 Example config files

`config/app.php`:

```php
<?php

return [
    'name' => 'Contacts API',
    'version' => '1.0.0',
];
```

`config/database.php`:

```php
<?php

return [
    'default' => 'main',
    'connections' => [
        'main' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => 5432,
            'database' => getenv('DB_NAME') ?: 'contacts_app',
            'username' => getenv('DB_USER') ?: 'app',
            'password' => getenv('DB_PASS') ?: '',
            'charset' => 'utf8',
        ],
        'analytics' => [
            'driver' => 'sqlite',
            'path' => '/tmp/contacts-analytics.sqlite',
        ],
    ],
];
```

`config/security.php`:

```php
<?php

return [
    'auth' => [
        'jwt' => ['enabled' => true],
        'opaque' => ['enabled' => true],
        'api_token' => ['enabled' => true],
        'cookie_session' => ['enabled' => true, 'cookie' => 'session_id'],
        'mtls' => ['enabled' => false],
    ],

    // jwt secret can come from secret file or env
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: '',
        'algorithms' => ['HS256'],
        'leeway_seconds' => 30,
        'issuer' => 'contacts-app',
        'audience' => 'contacts-clients',
    ],

    // token registries for in-memory strategies
    'tokens' => [
        'opaque' => [
            'opaque-admin-token' => [
                'subject' => 'admin-1',
                'roles' => ['admin'],
                'permissions' => ['contacts:read', 'contacts:write'],
                'token_id' => 'opaque-admin-id',
            ],
        ],
        'api' => [
            'api-key-123' => [
                'subject' => 'integration-service',
                'roles' => ['service'],
                'permissions' => ['contacts:read'],
            ],
        ],
    ],

    'sessions' => [
        'session-abc' => [
            'subject' => 'web-user-1',
            'roles' => ['user'],
            'permissions' => ['contacts:read'],
        ],
    ],

    'csrf' => [
        'enabled' => true,
        'methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
        'cookie' => 'csrf_token',
        'header' => 'x-csrf-token',
        'field' => '_csrf',
        'session_cookie' => 'session_id',
    ],

    'request' => [
        'max_body_bytes' => 1048576,
        'max_header_value_length' => 8192,
    ],

    'rate_limit' => [
        'limit' => 120,
        'window_seconds' => 60,
        'burst' => 0,
    ],

    'password' => [
        'algorithm' => 'argon2id',
        'options' => ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2],
    ],

    'headers' => [
        'x-frame-options' => 'DENY',
        'x-content-type-options' => 'nosniff',
    ],
];
```

### 3.2 Custom config loader + validator

```php
use Celeris\Framework\Config\ConfigLoader;
use Celeris\Framework\Config\ConfigValidator;
use Celeris\Framework\Config\EnvironmentLoader;

$validator = (new ConfigValidator())
    ->requireConfig('database.connections.main.driver')
    ->requireSecret('JWT_SECRET');

$loader = new ConfigLoader(
    __DIR__ . '/../config',
    new EnvironmentLoader(
        __DIR__ . '/../.env',
        __DIR__ . '/../secrets',
        false,
        true, // inject loaded values into $_ENV/$_SERVER for getenv-style config files
    ),
    $validator,
);

$kernel->setConfigLoader($loader);
```

### 3.3 Hot reload and hot restart

- Hot reload checks config snapshot fingerprint during `Kernel::reset()`.
- `Kernel::enableHotReload(false)` disables this behavior.
- `Kernel::hotRestart()` triggers shutdown + bootstrap re-run.

## 4. DI Container and Service Providers

The framework container supports lifetimes:
- `singleton`
- `request`
- `transient`

### 4.1 Lifetime semantics

Use lifetime intentionally, especially in worker mode where one process handles many requests.

`singleton`
- Created once per root container build and reused for all resolutions.
- Best for stateless shared infrastructure and expensive objects.
- Typical examples:
  - config repositories
  - serializers/validators
  - routers and shared registries
  - service classes that do not store request-specific mutable state
- Important in workers:
  - singleton instances can outlive many requests.
  - do not store per-request user/session/request payload state in singleton properties.

`request`
- Created once per request scope and reused only inside that request.
- `Kernel::handle()` creates a request-scoped container and clears it at request end.
- Best for request-bound state/services, for example:
  - correlation/request context wrappers
  - per-request unit of work objects
  - auth/session facades that cache request-local computation

`transient`
- New instance every time `get()` is called.
- Best for lightweight objects or short-lived builders that should not be shared.
- Typical examples:
  - response builders
  - command objects
  - pure helper objects where instance reuse has no value

Container guardrails:
- Circular dependencies are validated when container is rebuilt.
- A `singleton` cannot depend on a `request` service (explicitly blocked by container rules).
- Prefer constructor injection and keep lifetimes explicit in provider registration.

Quick decision guide:
- Choose `singleton` when the object is stateless (or safely shared) and reused broadly.
- Choose `request` when the object must be isolated per HTTP request.
- Choose `transient` when each resolution should be a fresh instance.

### 4.2 Registration examples by lifetime

```php
<?php

declare(strict_types=1);

namespace App;

use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;

final class LifetimeExamplesProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        // singleton: one instance shared across requests (until container rebuild)
        $services->singleton(
            \App\Shared\Clock::class,
            static fn (ContainerInterface $c): \App\Shared\Clock => new \App\Shared\Clock(),
        );

        // request: one instance per request
        $services->request(
            \App\Http\RequestAuditContext::class,
            static fn (ContainerInterface $c): \App\Http\RequestAuditContext
                => new \App\Http\RequestAuditContext(),
        );

        // transient: a new instance on every resolution
        $services->transient(
            \App\Http\ApiProblemBuilder::class,
            static fn (ContainerInterface $c): \App\Http\ApiProblemBuilder
                => new \App\Http\ApiProblemBuilder(),
        );
    }
}
```

Example of an invalid lifetime dependency (do not do this):

```php
$services->singleton(
    App\Bad\SingletonNeedingRequestState::class,
    static fn (ContainerInterface $c): App\Bad\SingletonNeedingRequestState
        => new App\Bad\SingletonNeedingRequestState(
            $c->get(App\Http\RequestAuditContext::class) // request-scoped
        ),
    [App\Http\RequestAuditContext::class],
);
```

This fails by design because a singleton cannot be built from a request-scoped dependency.

### 4.3 Provider example

`app/AppServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App;

use App\Contacts\ContactRepository;
use App\Contacts\ContactService;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\EntityManager;

final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            ContactRepository::class,
            static fn (ContainerInterface $c): ContactRepository
                => new ContactRepository($c->get(EntityManager::class), $c->get(DBAL::class)),
            [EntityManager::class, DBAL::class],
        );

        $services->singleton(
            ContactService::class,
            static fn (ContainerInterface $c): ContactService
                => new ContactService($c->get(ContactRepository::class)),
            [ContactRepository::class],
        );
    }
}
```

This is a repository-based provider example.
If you prefer service-first persistence, register `ContactService` directly with `EntityManager`/`DBAL` dependencies and skip `ContactRepository`.

Register provider in bootstrap:

```php
$kernel->registerProvider(new \App\AppServiceProvider());
```

### 4.4 Request scope notes

`Kernel::handle()` creates a request-scoped container and stores it in `RequestContext`.
Services registered with request lifetime are isolated per request and cleared at the end of that request.

## 5. HTTP Abstractions

### 5.1 Request

`Request` is immutable, with helpers:
- `getMethod()`, `getPath()`
- `getHeader()`, `headers()`
- `getQueryParam()`, `getParsedBody()`
- `getUploadedFile()`
- `withXxx()` methods that return copies

### 5.2 Response and builder

`Response` is immutable. `ResponseBuilder` is mutable and ergonomic.

```php
use Celeris\Framework\Http\ResponseBuilder;
use Celeris\Framework\Http\HttpStatus;

$response = (new ResponseBuilder())
    ->status(HttpStatus::CREATED)
    ->json(['id' => 10, 'ok' => true])
    ->build();
```

### 5.3 Cookies

```php
use Celeris\Framework\Http\SetCookie;

$response = $response->withCookie(
    (new SetCookie('session_id', 'abc123'))
        ->withHttpOnly(true)
        ->withSecure(true)
        ->withSameSite('Lax')
);
```

### 5.4 Streaming responses

```php
use Celeris\Framework\Http\ResponseBuilder;

$response = (new ResponseBuilder())
    ->header('content-type', 'text/plain; charset=utf-8')
    ->stream(function (callable $write): void {
        $write("chunk-1\n");
        $write("chunk-2\n");
    })
    ->build();
```

### 5.5 Content negotiation

```php
use Celeris\Framework\Http\ContentNegotiator;

$type = ContentNegotiator::negotiate(
    ['application/json', 'text/html'],
    $request->getHeader('accept'),
    'application/json'
);
```

### 5.6 PSR bridges (optional)

- `PsrRequestBridge::fromPsrRequest($psrRequest)`
- `PsrResponseBridge::toPsrResponse($response, $responseFactory, $streamFactory)`

## 6. Routing, Controllers, and Middleware

### 6.1 Programmatic routing

```php
use Celeris\Framework\Routing\RouteGroup;
use Celeris\Framework\Routing\RouteMetadata;

$kernel->groupRoutes(
    new RouteGroup(prefix: '/api', middleware: ['api.auth'], version: 'v1', tags: ['API']),
    function ($routes): void {
        $routes->get('/contacts', [\App\Contacts\ContactController::class, 'index']);

        $routes->get(
            '/contacts/{id}',
            [\App\Contacts\ContactController::class, 'show'],
            metadata: new RouteMetadata(
                name: 'contacts.show',
                summary: 'Get one contact',
                tags: ['Contacts'],
            )
        );
    }
);
```

### 6.2 Attribute routing

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Http;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;

#[RouteGroup(prefix: '/contacts', middleware: ['api.auth'], version: 'v1', tags: ['Contacts'])]
final class ContactController
{
    #[Route(methods: ['GET'], path: '/', summary: 'List contacts')]
    public function index(RequestContext $ctx, Request $request): Response
    {
        return new Response(200, ['content-type' => 'application/json; charset=utf-8'], '[]');
    }

    #[Route(methods: ['GET'], path: '/{id}', summary: 'Get contact')]
    public function show(RequestContext $ctx, Request $request, int $id): Response
    {
        return new Response(200, ['content-type' => 'application/json; charset=utf-8'], (string) json_encode(['id' => $id]));
    }
}
```

Register it:

```php
$kernel->registerController(\App\Contacts\Http\ContactController::class, new \Celeris\Framework\Routing\RouteGroup(prefix: '/api'));
```

### 6.3 Handler argument resolution

The kernel resolves handler parameters in this order:
- `RequestContext` type => current context
- `Request` type => current request
- container class type => resolved service
- DTO class marked with `#[Dto]` => mapped from payload/query
- path params by name (`{id}` -> `$id`), with scalar casting
- `array $params` or parameter named `params` => full path-param map

### 6.4 Middleware

Register middleware:

```php
$kernel->registerMiddleware('api.auth', new \App\Http\Middleware\RequireAuthMiddleware());
$kernel->addGlobalMiddleware('api.auth'); // optional global execution
```

`RequireAuthMiddleware` example:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function handle(RequestContext $ctx, Request $request, callable $next): Response
    {
        if ($ctx->getAuth() === null) {
            return new Response(401, ['content-type' => 'application/json; charset=utf-8'], '{"error":"unauthorized"}');
        }

        return $next($ctx, $request);
    }
}
```

### 6.5 Middleware introspection

```php
$all = $kernel->inspectMiddleware();
$routeOnly = $kernel->inspectMiddleware('GET', '/api/contacts/10', 'v1');
```

### 6.6 OpenAPI generation and validation

```php
$openApi = $kernel->generateOpenApi('Contacts API', '1.0.0');
$errors = $kernel->validateOpenApi($openApi);

if ($errors !== []) {
    throw new RuntimeException('OpenAPI invalid: ' . implode('; ', $errors));
}
```

## 7. API Project: End-to-End Example

This section is a complete API-style setup with CRUD, service classes, validation, auth, transactions, and Data Mapper ORM.
The framework supports two persistence styles:
- Service-first: keep persistence operations directly in the service class (common for simple CRUD).
- Service + repository: extract persistence to a repository when query complexity or reuse grows.

Install an API project:

```bash
composer create-project celeris/api users-service
```

This command installs `celeris/framework` into `vendor/celeris/framework`.
Set your runtime values in `.env` (or start from `.env.example`).

Default `.env` keys in the API scaffold:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_VERSION`
- `DB_DEFAULT`, `DB_SQLITE_PATH`
- `MYSQL_*`, `MARIADB_*`, `PGSQL_*`, `SQLSRV_*`
- `SECURITY_AUTH_*`, `SECURITY_CSRF_ENABLED`, `SECURITY_RATE_LIMIT_*`, `JWT_SECRET`, `JWT_LEEWAY_SECONDS`

### 7.1 Suggested structure

```text
api-app/
  .env
  .env.example
  public/
    index.php
  config/
    app.php
    database.php
    security.php
  app/
    AppServiceProvider.php
    Models/
      Contact.php
    Services/
      ContactService.php
    Repositories/ (optional)
      ContactRepository.php
    Http/
      Controllers/
        Api/
          ContactController.php
      Middleware/
        RequireAuthMiddleware.php
      DTOs/
        CreateContactDto.php
        UpdateContactDto.php
    Events/
      ContactCreatedEvent.php
```

Yes, this structure includes model classes.
In this API layout, model/entity classes live in `app/Models/` (for example `Contact.php`).

What each folder/file is for:
- `public/index.php`
- Front controller entrypoint. Boots kernel + runtime adapter and handles requests.
- `config/app.php`
- Application metadata and app-level settings.
- `config/database.php`
- Connection definitions (`default`, named `connections`, driver settings).
- `config/security.php`
- Security/auth/rate-limit/CSRF/password/hash settings.
- `app/AppServiceProvider.php`
- Composition root for your app services. Registers service/controller dependencies, and repositories only if you use that pattern.
- `app/Models/Contact.php`
- Domain entity model (Data Mapper style). Maps PHP object properties to table columns via ORM attributes.
- `app/Http/DTOs/CreateContactDto.php`
- Input contract for create operations. Used for request mapping + validation.
- `app/Http/DTOs/UpdateContactDto.php`
- Input contract for update operations.
- `app/Http/Controllers/Api/ContactController.php`
- HTTP transport layer for Contacts endpoints (routing, request/response orchestration).
- `app/Repositories/ContactRepository.php` (optional)
- Persistence access layer (EntityManager/DBAL queries) when you choose the repository pattern.
- `app/Services/ContactService.php`
- Business use-case layer. Coordinates validation-ready DTOs, persistence operations (directly or via repository), and transaction boundaries.
- `app/Http/Middleware/RequireAuthMiddleware.php`
- Shared HTTP middleware layer for auth guards and request preprocessing.

Recommended optional additions as the API grows:
- `app/Domain/Event/`
- Domain events emitted by your business layer.
- `app/Http/Requests/`
- HTTP request mapping classes for complex endpoints.
- `app/Database/Migration/`
- Database migrations used by `MigrationRunner`.

### 7.2 Domain model (Data Mapper style)

`app/Contacts/Domain/Contact.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Domain;

use Celeris\Framework\Database\ORM\Attribute\Column;
use Celeris\Framework\Database\ORM\Attribute\Entity;
use Celeris\Framework\Database\ORM\Attribute\Id;

#[Entity(table: 'contacts')]
final class Contact
{
    #[Id(generated: false)]
    #[Column('id')]
    public int $id;

    #[Column('first_name')]
    public string $firstName;

    #[Column('last_name')]
    public string $lastName;

    #[Column('phone')]
    public string $phone;

    #[Column('address')]
    public string $address;

    #[Column('age')]
    public int $age;
}
```

### 7.3 DTOs + validation

`app/Contacts/Dto/CreateContactDto.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Dto;

use Celeris\Framework\Serialization\Attribute\Dto;
use Celeris\Framework\Serialization\Attribute\MapFrom;
use Celeris\Framework\Validation\Attribute\Length;
use Celeris\Framework\Validation\Attribute\Range;
use Celeris\Framework\Validation\Attribute\Required;
use Celeris\Framework\Validation\Attribute\StringType;

#[Dto]
final class CreateContactDto
{
    public function __construct(
        #[Required]
        public int $id,

        #[Required, StringType, Length(min: 1, max: 100)]
        #[MapFrom('first_name')]
        public string $firstName,

        #[Required, StringType, Length(min: 1, max: 100)]
        #[MapFrom('last_name')]
        public string $lastName,

        #[Required, StringType, Length(min: 7, max: 30)]
        public string $phone,

        #[Required, StringType, Length(min: 5, max: 255)]
        public string $address,

        #[Required, Range(min: 0, max: 130)]
        public int $age,
    ) {}
}
```

`app/Contacts/Dto/UpdateContactDto.php` is similar with optional fields or strict required values depending your policy.

### 7.4 Persistence styles: service-first or repository + service

Most teams start with service-first persistence for simple CRUD because it is faster to read and maintain.
When the module grows, extract a repository without changing controller contracts.

Option A: service-first persistence (no repository yet).

`app/Services/ContactService.php` can inject `EntityManager` and/or `DBAL` directly and implement `list`, `getOrFail`, `create`, `update`, `remove` in one place.

Option B: repository + service split (shown below), useful for complex/reused queries.

`app/Contacts/ContactRepository.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts;

use App\Contacts\Domain\Contact;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\EntityManager;

final class ContactRepository
{
    public function __construct(
        private EntityManager $em,
        private DBAL $dbal,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function list(int $limit = 100, int $offset = 0): array
    {
        $query = $this->dbal->queryBuilder()
            ->select(['id', 'first_name', 'last_name', 'phone', 'address', 'age'])
            ->from('contacts')
            ->orderBy('id ASC')
            ->limit($limit)
            ->offset($offset)
            ->build();

        return $this->dbal->connection()->fetchAll($query->sql(), $query->params());
    }

    public function find(int $id): ?Contact
    {
        $entity = $this->em->find(Contact::class, $id);
        return $entity instanceof Contact ? $entity : null;
    }

    public function insert(Contact $contact): void
    {
        $this->em->persist($contact);
        $this->em->flush();
    }

    public function update(Contact $contact): void
    {
        $this->em->markDirty($contact);
        $this->em->flush();
    }

    public function delete(Contact $contact): void
    {
        $this->em->remove($contact);
        $this->em->flush();
    }
}
```

`app/Contacts/ContactService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts;

use App\Contacts\Domain\Contact;
use App\Contacts\Dto\CreateContactDto;
use App\Contacts\Dto\UpdateContactDto;
use RuntimeException;

final class ContactService
{
    public function __construct(private ContactRepository $repo) {}

    /** @return array<int, array<string, mixed>> */
    public function list(int $limit = 100, int $offset = 0): array
    {
        return $this->repo->list($limit, $offset);
    }

    public function getOrFail(int $id): Contact
    {
        $contact = $this->repo->find($id);
        if (!$contact instanceof Contact) {
            throw new RuntimeException('Contact not found.');
        }

        return $contact;
    }

    public function create(CreateContactDto $dto): Contact
    {
        $contact = new Contact();
        $contact->id = $dto->id;
        $contact->firstName = $dto->firstName;
        $contact->lastName = $dto->lastName;
        $contact->phone = $dto->phone;
        $contact->address = $dto->address;
        $contact->age = $dto->age;

        $this->repo->insert($contact);
        return $contact;
    }

    public function update(int $id, UpdateContactDto $dto): Contact
    {
        $contact = $this->getOrFail($id);
        $contact->firstName = $dto->firstName;
        $contact->lastName = $dto->lastName;
        $contact->phone = $dto->phone;
        $contact->address = $dto->address;
        $contact->age = $dto->age;

        $this->repo->update($contact);
        return $contact;
    }

    public function remove(int $id): void
    {
        $contact = $this->getOrFail($id);
        $this->repo->delete($contact);
    }
}
```

### 7.5 Controller (attribute routes + auth policy)

`app/Contacts/Http/ContactController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Http;

use App\Contacts\ContactService;
use App\Contacts\Dto\CreateContactDto;
use App\Contacts\Dto\UpdateContactDto;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;
use Celeris\Framework\Security\Authorization\Authorize;

#[RouteGroup(prefix: '/contacts', middleware: ['api.auth'], version: 'v1', tags: ['Contacts'])]
final class ContactController
{
    public function __construct(private ContactService $service) {}

    #[Route(methods: ['GET'], path: '/', summary: 'List contacts')]
    #[Authorize(roles: ['admin'])]
    public function index(RequestContext $ctx, Request $request): Response
    {
        $rows = $this->service->list(
            (int) ($request->getQueryParam('limit', 100)),
            (int) ($request->getQueryParam('offset', 0)),
        );

        return new Response(200, ['content-type' => 'application/json; charset=utf-8'], (string) json_encode($rows));
    }

    #[Route(methods: ['GET'], path: '/{id}', summary: 'Get one contact')]
    public function show(int $id): array
    {
        $contact = $this->service->getOrFail($id);
        return [
            'id' => $contact->id,
            'first_name' => $contact->firstName,
            'last_name' => $contact->lastName,
            'phone' => $contact->phone,
            'address' => $contact->address,
            'age' => $contact->age,
        ];
    }

    #[Route(methods: ['POST'], path: '/', summary: 'Create contact')]
    public function create(CreateContactDto $dto): Response
    {
        $contact = $this->service->create($dto);

        return new Response(
            201,
            ['content-type' => 'application/json; charset=utf-8'],
            (string) json_encode(['id' => $contact->id])
        );
    }

    #[Route(methods: ['PUT'], path: '/{id}', summary: 'Update contact')]
    public function update(int $id, UpdateContactDto $dto): array
    {
        $contact = $this->service->update($id, $dto);

        return [
            'id' => $contact->id,
            'first_name' => $contact->firstName,
            'last_name' => $contact->lastName,
            'phone' => $contact->phone,
            'address' => $contact->address,
            'age' => $contact->age,
        ];
    }

    #[Route(methods: ['DELETE'], path: '/{id}', summary: 'Delete contact')]
    public function delete(int $id): Response
    {
        $this->service->remove($id);
        return new Response(204);
    }
}
```

### 7.6 API bootstrap wiring

`public/index.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\AppServiceProvider;
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
$kernel->registerController(ContactController::class, new RouteGroup(prefix: '/api'));

// Export OpenAPI at boot time (optional)
$openApi = $kernel->generateOpenApi('Contacts API', '1.0.0');
$errors = $kernel->validateOpenApi($openApi);
if ($errors !== []) {
    throw new RuntimeException('OpenAPI validation failed: ' . implode('; ', $errors));
}

$runner = new WorkerRunner($kernel, new FPMAdapter());
$runner->run();
```

## 8. MVC Project: End-to-End Example

There is no mandatory template engine in core. MVC views are typically plain PHP templates rendered by a service class.

Install an MVC project:

```bash
composer create-project celeris/mvc blog
```

This command installs `celeris/framework` into `vendor/celeris/framework`.
Set your runtime values in `.env` (or start from `.env.example`).

Default `.env` keys in the MVC scaffold:
- `APP_NAME`, `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE`, `APP_VERSION`
- `DB_DEFAULT`, `DB_SQLITE_PATH`
- `MYSQL_*`, `MARIADB_*`, `PGSQL_*`, `SQLSRV_*`
- `SECURITY_AUTH_*`, `SECURITY_CSRF_ENABLED`, `SECURITY_RATE_LIMIT_*`, `JWT_SECRET`, `JWT_LEEWAY_SECONDS`

### 8.1 Suggested structure

```text
mvc-app/
  .env
  .env.example
  public/
    index.php
  config/
    app.php
    database.php
    security.php
  app/
    AppServiceProvider.php
    Models/
      Contact.php
    Services/
      ContactService.php
    Repositories/ (optional)
      ContactRepository.php
    Http/
      Controllers/
        ContactPageController.php
      Middleware/
        RequireAuthMiddleware.php
    Shared/
      ViewRenderer.php
    Views/
      layout.php
      contacts/
        index.php
        show.php
```

Yes, this structure also includes model classes.
In this MVC layout, model/entity classes live in `app/Models/` (for example `Contact.php`).

What each folder/file is for:
- `public/index.php`
- Front controller entrypoint. Boots kernel + runtime adapter and serves MVC routes.
- `config/app.php`
- Application metadata and app-level configuration.
- `config/database.php`
- Database connection definitions used by repository/service layer.
- `config/security.php`
- Security/auth/rate-limit/CSRF settings (important for form submissions and session-based flows).
- `app/AppServiceProvider.php`
- Registers MVC services (renderer, domain services, controllers, and optional repositories).
- `app/Shared/ViewRenderer.php`
- Shared rendering service for PHP template files in `app/Views`.
- `app/Models/Contact.php`
- Domain entity model (Data Mapper style) mapped to database table columns.
- `app/Repositories/ContactRepository.php` (optional)
- Persistence/data access for contacts when you choose the repository pattern.
- `app/Services/ContactService.php`
- Business/use-case orchestration between controller and persistence (direct DBAL/EntityManager or repository).
- `app/Http/Controllers/ContactPageController.php`
- MVC controller that prepares view models and returns HTML responses.
- `app/Http/Middleware/RequireAuthMiddleware.php`
- Middleware layer for auth/session checks and request-level guards.
- `app/Views/layout.php`
- Optional shared layout/chrome template used by page views.
- `app/Views/contacts/index.php`
- Contact listing and form page.
- `app/Views/contacts/show.php`
- Contact details page.

Recommended optional additions as the MVC module grows:
- `app/Http/Form/`
- Form request mapping/DTO classes for complex forms.
- `app/Domain/Event/`
- Domain events for side effects/auditing.
- `app/Database/Migration/`
- Database migration files used by `MigrationRunner`.

### 8.2 View renderer service

`app/Shared/ViewRenderer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Shared;

final class ViewRenderer
{
    public function __construct(private string $viewsPath) {}

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data = []): string
    {
        $file = rtrim($this->viewsPath, '/') . '/' . trim($view, '/') . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: {$file}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
```

### 8.3 MVC controller

`app/Contacts/Http/ContactPageController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Http;

use App\Contacts\ContactService;
use App\Shared\ViewRenderer;
use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\SetCookie;
use Celeris\Framework\Routing\Attribute\Route;
use Celeris\Framework\Routing\Attribute\RouteGroup;

#[RouteGroup(prefix: '/contacts', version: 'v1')]
final class ContactPageController
{
    public function __construct(
        private ContactService $service,
        private ViewRenderer $views,
    ) {}

    #[Route(methods: ['GET'], path: '/', summary: 'Contacts page')]
    public function index(RequestContext $ctx, Request $request): Response
    {
        $rows = $this->service->list(50, 0);
        $csrf = bin2hex(random_bytes(16));

        $html = $this->views->render('contacts/index', [
            'contacts' => $rows,
            'csrfToken' => $csrf,
        ]);

        $response = new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);

        return $response->withCookie(
            (new SetCookie('csrf_token', $csrf))
                ->withHttpOnly(false)
                ->withSecure(false)
                ->withSameSite('Lax')
        );
    }

    #[Route(methods: ['GET'], path: '/{id}', summary: 'Contact details page')]
    public function show(int $id): Response
    {
        $contact = $this->service->getOrFail($id);

        $html = $this->views->render('contacts/show', ['contact' => $contact]);
        return new Response(200, ['content-type' => 'text/html; charset=utf-8'], $html);
    }
}
```

### 8.4 MVC views

`app/Views/contacts/index.php`:

```php
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Contacts</title>
</head>
<body>
  <h1>Contacts</h1>

  <form method="post" action="/contacts" accept-charset="utf-8">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="text" name="first_name" placeholder="First name">
    <input type="text" name="last_name" placeholder="Last name">
    <button type="submit">Create</button>
  </form>

  <ul>
    <?php foreach ($contacts as $row): ?>
      <li>
        <a href="/contacts/<?= (int) $row['id'] ?>">
          <?= htmlspecialchars((string) $row['first_name'] . ' ' . (string) $row['last_name'], ENT_QUOTES, 'UTF-8') ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</body>
</html>
```

`app/Views/contacts/show.php`:

```php
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Contact</title></head>
<body>
  <h1><?= htmlspecialchars($contact->firstName . ' ' . $contact->lastName, ENT_QUOTES, 'UTF-8') ?></h1>
  <p>Phone: <?= htmlspecialchars($contact->phone, ENT_QUOTES, 'UTF-8') ?></p>
  <p>Address: <?= htmlspecialchars($contact->address, ENT_QUOTES, 'UTF-8') ?></p>
  <p>Age: <?= (int) $contact->age ?></p>
</body>
</html>
```

### 8.5 MVC provider wiring

`app/AppServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App;

use App\Contacts\ContactRepository;
use App\Contacts\ContactService;
use App\Shared\ViewRenderer;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceProviderInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Database\DBAL;
use Celeris\Framework\Database\ORM\EntityManager;

final class AppServiceProvider implements ServiceProviderInterface
{
    public function register(ServiceRegistry $services): void
    {
        $services->singleton(
            ViewRenderer::class,
            static fn (ContainerInterface $c): ViewRenderer => new ViewRenderer(__DIR__ . '/Views'),
        );

        $services->singleton(
            ContactRepository::class,
            static fn (ContainerInterface $c): ContactRepository => new ContactRepository(
                $c->get(EntityManager::class),
                $c->get(DBAL::class)
            ),
            [EntityManager::class, DBAL::class],
        );

        $services->singleton(
            ContactService::class,
            static fn (ContainerInterface $c): ContactService => new ContactService($c->get(ContactRepository::class)),
            [ContactRepository::class],
        );
    }
}
```

This provider example uses `ContactRepository`, but it is optional.
For simple modules, inject `EntityManager`/`DBAL` directly into `ContactService` and register only the service.

## 9. Database and ORM

### 9.1 Supported database engines

Supported `DatabaseDriver` values:
- `mysql`
- `mariadb`
- `pgsql`
- `sqlite`
- `sqlsrv`

### 9.2 Connection management

- `Kernel::getConnectionPool()` gives access to configured named connections
- `Kernel::getDbal()->connection('name')` resolves one connection
- The kernel’s default `EntityManager` uses `database.default`

For a non-default mapper connection:

```php
$dbal = $kernel->getDbal();
$analyticsConnection = $dbal->connection('analytics');
$analyticsEm = new \Celeris\Framework\Database\ORM\EntityManager($analyticsConnection);
```

### 9.3 DBAL query builder

```php
$dbal = $kernel->getDbal();
$query = $dbal->queryBuilder()
    ->select(['id', 'first_name'])
    ->from('contacts')
    ->where('age >= :age', ['age' => 18])
    ->orderBy('id DESC')
    ->limit(20)
    ->build();

$rows = $dbal->connection()->fetchAll($query->sql(), $query->params());
```

### 9.4 Data Mapper CRUD

```php
$em = $kernel->getEntityManager();

$contact = new Contact();
$contact->id = 100;
$contact->firstName = 'Ada';
$contact->lastName = 'Lovelace';
$contact->phone = '+1-555-0100';
$contact->address = 'Example St';
$contact->age = 36;

$em->persist($contact);
$em->flush();

$loaded = $em->find(Contact::class, 100);
$loaded->phone = '+1-555-0199';
$em->markDirty($loaded);
$em->flush();

$em->remove($loaded);
$em->flush();
```

### 9.5 Lazy relations

```php
use Celeris\Framework\Database\ORM\Attribute\LazyRelation;
use Celeris\Framework\Database\ORM\LazyReference;

#[Entity(table: 'orders')]
final class Order
{
    #[Id(generated: false)]
    #[Column('id')]
    public int $id;

    #[Column('contact_id')]
    public int $contactId;

    #[LazyRelation(targetEntity: Contact::class, localKey: 'contactId', targetKey: 'id')]
    public LazyReference $contact;
}

$order = $em->find(Order::class, 1);
$contact = $em->loadRelation($order, 'contact');
```

### 9.6 Transactions

#### A) Data Mapper unit-of-work transaction

Each `EntityManager::flush()` executes in a transaction.

#### B) Explicit multi-step transaction

```php
$connection = $kernel->getDbal()->connection('main');

$connection->transactional(function ($conn) use ($em, $contact): void {
    $em->persist($contact);
    $em->flush();

    $conn->execute(
        'INSERT INTO audit_log (action, entity_id) VALUES (:action, :entity_id)',
        ['action' => 'contact_created', 'entity_id' => $contact->id]
    );
});
```

### 9.7 Query tracing and hidden-query checks

```php
use Celeris\Framework\Database\Connection\QueryTraceInspector;

$inspector = new QueryTraceInspector($kernel->getDbal()->connection()->tracer());
$snapshot = $inspector->snapshot();

// run operation
$rows = $repo->list();

$queries = $inspector->queriesSince($snapshot);
```

### 9.8 Migrations

```php
$migrationRunner = $kernel->getMigrationRunner();
$result = $migrationRunner->migrate([
    new CreateContactsMigration(),
]);
```

## 10. Optional Active Record Compatibility Layer

The core ORM is Data Mapper. Active Record is optional and additive.

### 10.1 Enable AR provider

```php
use Celeris\Framework\Database\ActiveRecord\ActiveRecordServiceProvider;

$kernel->registerProvider(new ActiveRecordServiceProvider());
```

### 10.2 AR model example

```php
<?php

declare(strict_types=1);

namespace App\Contacts\Domain;

use Celeris\Framework\Database\ActiveRecord\ActiveRecordModel;
use Celeris\Framework\Database\ORM\Attribute\Column;
use Celeris\Framework\Database\ORM\Attribute\Entity;
use Celeris\Framework\Database\ORM\Attribute\Id;

#[Entity(table: 'contacts')]
final class ContactAr extends ActiveRecordModel
{
    #[Id(generated: false)]
    #[Column('id')]
    private int $id;

    #[Column('first_name')]
    private string $firstName;

    #[Column('last_name')]
    private string $lastName;

    #[Column('phone')]
    private string $phone;

    #[Column('address')]
    private string $address;

    #[Column('age')]
    private int $age;

    public static function connectionName(): ?string
    {
        return 'main';
    }
}
```

Usage:

```php
$contact = ContactAr::create([
    'id' => 1,
    'firstName' => 'Ada',
    'lastName' => 'Lovelace',
    'phone' => '+1-555-0100',
    'address' => 'Example',
    'age' => 36,
]);

$contact->phone = '+1-555-0199';
$contact->save();

$found = ContactAr::where('age', 36)->orderBy('id', 'DESC')->first();
$contact->delete();
```

## 11. Security Subsystem

Security pipeline in `SecurityKernelGuard` enforces, in order:
1. Input normalization
2. Request validation
3. SQL injection input guard
4. Rate limiting
5. Authentication strategy resolution
6. Contextual CSRF enforcement
7. Route authorization policies
8. Security response headers finalization

### 11.1 Authentication strategies supported

- JWT bearer token (`JwtTokenStrategy`)
- Opaque bearer token (`OpaqueTokenStrategy`)
- API token via header/query (`ApiTokenStrategy`)
- Cookie sessions (`CookieSessionStrategy`)
- mTLS (`MutualTlsStrategy`)

### 11.2 Authorization with attributes

```php
use Celeris\Framework\Security\Authorization\Authorize;

final class AdminController
{
    #[Authorize(roles: ['admin'])]
    public function dashboard(): array
    {
        return ['ok' => true];
    }

    #[Authorize(permissions: ['contacts:write'])]
    public function writeAction(): array
    {
        return ['ok' => true];
    }

    #[Authorize(strategies: ['cookie_session'])]
    public function webOnlyAction(): array
    {
        return ['ok' => true];
    }
}
```

### 11.3 Token revocation

```php
$authEngine = $kernel->getSecurityGuard()->authEngine();
$authEngine->revokeToken('token-id-123');
```

### 11.4 Password hashing

```php
$hasher = $kernel->getSecurityGuard()->passwordHasher();
$hash = $hasher->hash('S3cure-P@ssw0rd');
$ok = $hasher->verify('S3cure-P@ssw0rd', $hash);
```

## 12. Validation and Serialization

### 12.1 Attribute validation

Use `ValidatorEngine` directly:

```php
$validator = $kernel->getValidator();
$result = $validator->validate($dto);
if (!$result->isValid()) {
    // inspect $result->errors()
}
```

or strict mode:

```php
$kernel->getValidator()->assertValid($dto);
```

### 12.2 DTO mapping in handlers

If a handler parameter is typed with a class annotated `#[Dto]`, the kernel maps request payload/query into it automatically and validates it.

### 12.3 Deterministic serialization

```php
$serializer = $kernel->getSerializer();
$json = $serializer->toJson($domainObject);
```

Serializer normalizes arrays/objects deterministically and supports enum/date conversion.

## 13. Caching and HTTP Cache Semantics

### 13.1 Cache engine from intents

```php
use Celeris\Framework\Cache\Intent\CacheIntent;

$cache = $kernel->getCacheEngine();

$intent = CacheIntent::read('contacts', 'contact:100', ttlSeconds: 60, tags: ['contact:100'])
    ->withPublic(true)
    ->withStaleWhileRevalidate(30);

$value = $cache->remember($intent, fn () => $service->getOrFail(100));
```

### 13.2 Deterministic invalidation

```php
$cache->invalidate(CacheIntent::invalidate('contacts', '*', ['contact:100']));
```

### 13.3 HTTP cache policy from request context

```php
use Celeris\Framework\Cache\Http\HttpCacheContext;

$ctx = HttpCacheContext::withIntent($ctx, $intent);
```

`HttpCacheHeadersFinalizer` will emit `Cache-Control`, `Vary`, `ETag`, and `Last-Modified` accordingly.

## 14. Domain Events

### 14.1 Define and dispatch events

```php
use Celeris\Framework\Domain\Event\AbstractDomainEvent;

final class ContactCreatedEvent extends AbstractDomainEvent
{
    public function __construct(private int $contactId)
    {
        parent::__construct();
    }

    public function payload(): array
    {
        return ['contact_id' => $this->contactId];
    }
}

$dispatcher = $kernel->getDomainEventDispatcher();
$dispatcher->listen(ContactCreatedEvent::class, function (ContactCreatedEvent $event): void {
    // side effects
});
$dispatcher->dispatch(new ContactCreatedEvent(100));
```

`EntityManager::flush()` also forwards domain events if your entity exposes `pullDomainEvents()` or `releaseDomainEvents()`.

## 15. Tooling Platform (CLI + Web)

### 15.1 CLI

`packages/framework/bin/celeris` supports:
- `list-generators`
- `graph`
- `validate`
- `generate`

Examples:

```bash
php packages/framework/bin/celeris list-generators
php packages/framework/bin/celeris graph --format=dot
php packages/framework/bin/celeris validate
php packages/framework/bin/celeris generate controller Contact --module=Contacts
php packages/framework/bin/celeris generate module Billing --write
```

### 15.2 Web tooling endpoint

You can mount `DeveloperUiController` as route handler:

```php
use Celeris\Framework\Tooling\ToolingPlatform;

$platform = ToolingPlatform::fromProjectRoot(__DIR__ . '/..');
$toolingUi = $platform->webUi('/__dev/tooling');

$kernel->routes()->get('/__dev/tooling', $toolingUi);
$kernel->routes()->get('/__dev/tooling/graph', $toolingUi);
$kernel->routes()->get('/__dev/tooling/validate', $toolingUi);
$kernel->routes()->get('/__dev/tooling/generate/preview', $toolingUi);
```

## 16. Distributed Features (Microservice/SOA)

`MicroserviceRuntimeModel` provides built-in middleware stack for:
- request IDs
- distributed tracing propagation
- observability hooks
- service-to-service authentication

### 16.1 Example

```php
use Celeris\Framework\Distributed\MicroserviceRuntimeModel;
use Celeris\Framework\Distributed\Auth\ServiceAuthenticator;
use Celeris\Framework\Distributed\Messaging\InMemoryMessageBus;
use Celeris\Framework\Distributed\Tracing\InMemoryTracer;
use Celeris\Framework\Distributed\Tracing\W3CTraceContextPropagator;
use Celeris\Framework\Distributed\Observability\ObservabilityDispatcher;

$runtime = new MicroserviceRuntimeModel(
    serviceName: 'contacts-service',
    serviceSecret: 'contacts-secret',
    inboundAuthenticator: new ServiceAuthenticator(['gateway' => 'gateway-secret']),
    messageBus: new InMemoryMessageBus(),
    tracer: new InMemoryTracer(),
    propagator: new W3CTraceContextPropagator(),
    observability: new ObservabilityDispatcher(),
    requireServiceAuth: true,
);
```

For outbound service calls:

```php
$outbound = $runtime->prepareOutboundRequest($ctx, $request);
```

For messaging:

```php
$runtime->publishMessage($ctx, 'contacts.events', 'contact.created', ['id' => 100]);
```

## 17. Transactions, CRUD, and Connection Patterns (Decision Guide)

Use these rules:
- Single aggregate write with Data Mapper: `persist/markDirty/remove + flush`
- Multiple writes that must be atomic with extra SQL: `connection()->transactional(...)`
- Read-heavy endpoints: DBAL query builder + projection arrays
- Simple CRUD module: keep persistence in service class first; extract repository when queries/reuse grow
- Multi-DB app: separate named connections in `database.connections`
- EntityManager default uses `database.default`; instantiate a dedicated `EntityManager` for non-default mapper workflows
- Optional AR compatibility: enable `ActiveRecordServiceProvider`; use `connectionName()` per model

## 18. API vs MVC Cheat Sheet

- API:
  - return JSON (`Response`, arrays/objects auto-serialized)
  - prefer DTO mapping + validation attributes
  - route auth via `#[Authorize]`
  - explicit service layer; repositories are optional and usually extracted when persistence logic grows

- MVC:
  - return HTML responses
  - render `app/Views/*.php` via `ViewRenderer` service
  - use cookie sessions + CSRF for form routes
  - controllers orchestrate service + view model

## 19. Operational Checklist

Before production:
1. Configure security secrets (`JWT_SECRET`, DB passwords) through `secrets/` and/or env
2. Set strict security config and rate limits
3. Validate OpenAPI output during CI
4. Add integration tests for auth + CSRF + rate-limit behavior
5. Add migration workflow checks
6. Add query tracing checks for hidden query regressions
7. Enable deterministic cache invalidation strategy for multi-worker deployment
8. If using worker mode, validate reset hooks and hot-reload behavior

## 20. Reference Map

Most-used classes by subsystem:
- Kernel/runtime: `Kernel`, `WorkerRunner`, `FPMAdapter`, `RoadRunnerAdapter`, `SwooleAdapter`
- DI: `ServiceRegistry`, `Container`, `ServiceProviderInterface`, `BootableServiceProviderInterface`
- HTTP: `Request`, `Response`, `ResponseBuilder`, `SetCookie`, `ContentNegotiator`, PSR bridges
- Routing: `Router`, `RouteCollector`, `RouteGroup`, `AttributeRouteLoader`, route attributes, `OpenApiGenerator`
- Middleware: `Pipeline`, `MiddlewareDispatcher`, `MiddlewareInterface`
- Config: `ConfigLoader`, `EnvironmentLoader`, `ConfigRepository`, `ConfigValidator`
- Security: `SecurityKernelGuard`, `AuthEngine`, auth strategies, `PolicyEngine`, `Authorize`, `RateLimiter`, `PasswordHasher`
- Validation/serialization: `ValidatorEngine`, validation attributes, `DtoMapper`, `Serializer`
- Database/ORM: `DBAL`, `ConnectionPool`, `EntityManager`, ORM attributes, `MigrationRunner`
- Optional AR: `ActiveRecordServiceProvider`, `ActiveRecordModel`, `ActiveRecordManager`, `ActiveRecordQuery`
- Cache: `CacheEngine`, `CacheIntent`, `DeterministicInvalidationEngine`, `HttpCacheHeadersFinalizer`
- Domain events: `DomainEventDispatcher`, `AbstractDomainEvent`
- Tooling: `ToolingPlatform`, `ToolingCliApplication`, `DeveloperUiController`
- Distributed: `MicroserviceRuntimeModel`, `ServiceAuthenticator`, tracing, observability, message bus

---

If you want, this manual can be split next into:
- `docs/manual/api-guide.md`
- `docs/manual/mvc-guide.md`
- `docs/manual/security-guide.md`
- `docs/manual/database-guide.md`

with copy-paste-ready project templates.
