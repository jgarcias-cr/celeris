# ADR 003 — Middleware model

Status: Proposed

Context
  - API-first frameworks need a composable, predictable request processing pipeline.

Decision
  - Implement a middleware pipeline where each middleware is a typed object implementing `MiddlewareInterface` with a single method `handle(RequestContext $ctx, Request $req, callable $next): Response`.
  - Middleware must be pure regarding the `RequestContext` — they may return a modified copy of the context to the next stage.
  - Pipeline ordering is explicit; middleware registration happens in module bootstrap code and is deterministic.
  - Short-circuiting responses are allowed; middleware should call `$next($ctx, $req)` to continue.

Consequences
  - Encourages small, testable middleware units.
  - Explicit ordering reduces surprising behavior and improves traceability.
