# Architectural Blueprint — Phase 0

See also: `docs/user-manual.md` for end-to-end implementation guidance and examples.

Summary
  - API-first, stateless-by-default, strict-typing, DI-centric framework that runs on both PHP-FPM and worker runtimes (RoadRunner, Swoole).

Kernel
  - Composition root that wires the container, module service providers, router, middleware pipeline, and event bus.
  - Exposes a single `handle(RequestContext, Request): Response` entrypoint.

Request Flow
  1. Worker/FPM receives raw request
  2. Adapter normalizes into `Request` and the kernel creates `RequestContext`
  3. Router resolves handler metadata
  4. Middleware pipeline executes in registered order
  5. Handler resolved from container and executed
  6. Response finalizers run (headers, instrumentation)
  7. Response serialized and returned

Worker Runtime
  - Adapter layer abstracts differences between PHP-FPM and worker runtimes. Adapters implement lightweight reset hooks used between requests.

Extensibility
  - Modules register `ServiceProviders` to add services, middleware, routes, and event subscribers.
  - No hidden globals: explicit dependency injection and request context passing.

Observability
  - Core hooks for metrics, tracing, and logging are provided as services and can be swapped.
