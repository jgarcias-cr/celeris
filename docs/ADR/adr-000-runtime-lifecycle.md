# ADR 000 — Runtime lifecycle

Status: Proposed

Context
  - Framework targets PHP 8.4 and must support both traditional FPM and worker runtimes (RoadRunner, Swoole).
  - API-first, stateless-by-default design; one process should be able to serve many requests.

Decision
  - The kernel will implement a composable lifecycle with explicit phases: boot -> bootstrap -> handleRequest -> shutdown.
  - Boot: create the dependency container and register core services (config, logger, container, routing, event-bus).
  - Bootstrap: load modules (register service providers), compile/validate container if needed.
  - handleRequest: for each request create an immutable RequestContext, resolve the entrypoint handler from the Router, execute middleware pipeline, dispatch to handler, collect Response, run response finalizers.
  - Shutdown: run lightweight cleanup hooks; full process exit only in FPM/CLI. Worker runtimes call a lightweight reset between requests (no full process restart).

Consequences
  - No globals: request-scoped data lives in `RequestContext` only and is injected where needed.
  - Worker adapters implement lightweight context reset hooks and a deterministic lifecycle to avoid leaks.
