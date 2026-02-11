# ADR 002 — Dependency container model

Status: Proposed

Context
  - Framework must be PSR-compatible, strict typed, and favor DI as a first-class primitive.

Decision
  - Adopt a lightweight, PSR-11-compatible container implementation with the following characteristics:
    - Lazy resolution by default.
    - Support for service definitions, factories, and autowiring opt-in.
    - Separate compile step available for production to freeze definitions and validate circular dependencies.
    - Services are registered via `ServiceProvider` classes; providers declare services and any bootstrap hooks.
    - Container offers a `get(RequestContext $ctx, string $id)` overload/variant for resolving request-scoped services bound to a `RequestContext`.

Consequences
  - Encourages explicit service registration and avoids hidden global resolution.
  - Allows worker runtimes to provide request-scoped bindings without polluting global container state.
