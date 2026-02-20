# Architectural Blueprint — Current

See also: `docs/user-manual.md` for end-to-end implementation guidance and examples.

Summary
  - API-first, stateless-by-default, strict-typing, DI-centric framework.
  - One programming model for `FPM` and worker runtimes through a shared `Kernel + WorkerRunner` lifecycle.
  - Native worker mode is first-party (`NativeHttpWorkerAdapter`) and external runtimes (`RoadRunner`/`Swoole`) are optional integrations.

Repository Shape
  - Monorepo with core framework in `packages/framework`.
  - First-party app stubs in `packages/api-stub` and `packages/mvc-stub`.
  - Optional capability packages (notifications, queue manager, realtime gateway, pulse sample) in `packages/*`.

Kernel
  - `Kernel` is the composition root and orchestrates container, providers, routing, middleware, security gates, handler dispatch, and response finalization.
  - Primary entrypoint: `handle(RequestContext, Request): Response`.
  - Domain events are handled via `DomainEventDispatcher` (not a standalone EventBus module).

Runtime Model
  - `WorkerRunner` orchestrates process lifecycle:
    1. `kernel.boot()` once
    2. `adapter.start()` once
    3. request loop (`nextRequest -> handle -> send`)
    4. deterministic per-request reset (`kernel.reset()`, `adapter.reset()`)
    5. `adapter.stop()` and `kernel.shutdown()` on exit
  - Runtime adapters:
    - `FPMAdapter` (single request frame per invocation)
    - `NativeHttpWorkerAdapter` (first-party socket HTTP/1.1 worker loop)
    - `RoadRunnerAdapter` (callback bridge)
    - `SwooleAdapter` (callback bridge)

Request Flow
  1. Runtime receives raw request.
  2. Adapter creates `RequestContext` and `Request`, yielding `RuntimeRequest`.
  3. `WorkerRunner` invokes `Kernel::handle(ctx, request)`.
  4. Security pre-checks, routing, and middleware pipeline execute in deterministic order.
  5. Handler is resolved from container and executed.
  6. Response finalizers run (security and HTTP cache headers, other configured finalizers).
  7. Adapter emits response and reset hooks run before next request.

Core Modules (`packages/framework/src`)
  - `Cache`, `Config`, `Container`, `Database`, `Distributed`, `Domain`, `Http`, `Kernel`, `Middleware`, `Notification`, `Routing`, `Runtime`, `Security`, `Serialization`, `Tooling`, `Validation`, `View`.

Extensibility
  - Service providers register optional services, middleware, routes, and package integrations.
  - Optional packages extend capabilities without forcing dependencies into `celeris/framework`.
  - Runtime-specific integrations remain adapter-driven and explicit.

Observability
  - Distributed and tooling modules provide tracing/observability hooks and dependency graph tooling.
  - Operational guidance and security/runbook material live under `docs/runbooks` and `docs/security`.
