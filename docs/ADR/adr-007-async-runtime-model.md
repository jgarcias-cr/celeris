# ADR 007 — Async runtime model

Status: Proposed

Context
  - Celeris currently executes request handling synchronously per request frame.
  - Some service workloads require high-concurrency I/O fan-out with bounded latency and cancellation semantics.
  - The framework must preserve deterministic worker safety (`boot -> handle -> reset`) while enabling async execution.
  - Existing applications must continue to run without forced rewrites during the migration period.

Decision
  - Introduce an async-first runtime contract while keeping a first-class sync compatibility path.
  - Add an awaitable abstraction in core contracts:
    - `RequestHandlerInterface::handleAsync(RequestContext $ctx, Request $req): Awaitable<Response>`
    - `MiddlewareInterface::handleAsync(RequestContext $ctx, Request $req, callable $next): Awaitable<Response>`
    - Runtime adapters may execute either async or sync pipelines, but `Kernel` exposes async entrypoints as canonical.
  - Keep sync contracts available for staged migration:
    - `handle(...) -> Response` remains valid in v1 async transition.
    - Sync handlers/middleware are wrapped by an adapter bridge into resolved awaitables.
  - Standardize request-scoped cooperative cancellation and deadlines:
    - `RequestContext` deadline is authoritative when present.
    - Cancellation token is propagated through middleware, handlers, and framework clients.
    - Cancellation must fail fast with a dedicated timeout/cancel response policy.
  - Enforce backpressure and overload policy in runtime adapters:
    - Bounded in-flight request concurrency per worker.
    - Queue admission strategy with configurable reject/timeout behavior.
    - Deterministic cleanup on cancellation and overload short-circuit.
  - Define async-safe service boundaries:
    - Framework-provided clients (HTTP, cache, queue, DB) must expose non-blocking variants.
    - Blocking calls are allowed only through explicit sync bridges and are marked as migration paths.
  - Preserve deterministic lifecycle guarantees:
    - `kernel.boot()` once per worker, async request loop per frame, `kernel.reset()` after each frame, `kernel.shutdown()` on stop.
    - Async tasks tied to a request must complete, cancel, or detach according to policy before `reset()`.
  - Observability is mandatory in async mode:
    - Trace context and request id propagation across awaited boundaries.
    - Metrics for in-flight count, queue depth, cancellation, timeout, and overload reject rates.

Compatibility and Migration Rules
  - Migration is incremental, not a flag day rewrite.
  - Versioning policy:
    - Phase 1: dual sync+async contracts, sync default allowed.
    - Phase 2: async-first defaults in stubs/tooling; sync path supported but discouraged.
    - Phase 3: sync-only extension points may be deprecated in a major release.
  - Existing packages remain compatible via adapter shims as long as they do not rely on hidden global state.
  - New framework extension points must provide async-capable interfaces.

Non-goals
  - This ADR does not select a specific third-party event loop implementation.
  - This ADR does not define concrete package-level APIs for each infrastructure client.
  - This ADR does not change Celeris security or authorization semantics.

Consequences
  - Enables high-concurrency I/O request handling with explicit cancellation and deadline semantics.
  - Increases framework complexity and testing scope (race conditions, cancellation safety, resource cleanup).
  - Requires adapter and client ecosystem updates, plus static/runtime checks for blocking behavior in async paths.
  - Maintains adoption safety by preserving sync compatibility during migration phases.

Acceptance Criteria
  - Kernel and middleware contracts include async entrypoints with documented sync bridges.
  - Runtime adapters implement bounded concurrency and cancellation-aware request teardown.
  - Request context propagation includes deadline and cancellation token semantics.
  - Observability captures async execution boundaries and overload metrics.
  - Migration guide defines phases, compatibility matrix, and deprecation policy.
