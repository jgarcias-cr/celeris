# ADR 004 — Event model

Status: Proposed

Context
  - Framework features (logging, metrics, lifecycle hooks) need asynchronous, decoupled communication without hidden coupling.

Decision
  - Provide a strongly-typed Event Bus with synchronous and asynchronous dispatch variants.
  - Events are plain immutable DTOs; listeners subscribe to event types via `EventSubscriber` definitions.
  - The event bus is delivered via DI; modules can opt into async dispatch backed by a queue (worker-processed) or synchronous dispatch for fast in-process handlers.
  - Prioritize explicit wiring: subscriber registration happens in service providers.

Consequences
  - Clear tracing of side effects; no global pub/sub hidden in libraries.
  - Async workflows are explicit and observable.
