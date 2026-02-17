# Module Boundaries

Kernel
  - Composition root only; orchestrates lifetime and wires modules. No business logic.

Http
  - Request and response representations, adapters converting runtime-specific requests (native/Swoole/RR/FPM) into framework `Request`.

Routing
  - Route definitions, matching algorithms, and route metadata. Should not perform DI resolution of handlers directly; only return metadata needed to resolve handler from container.

Container
  - Service registration, providers, and optional compilation. Provides request-scoped resolution hooks.

Middleware
  - Cross-cutting concerns implemented as middleware — auth, rate limiting, metrics, CORS.

Event
  - Event definitions and subscribers; async queue adapters live in `Worker` or `Queue` modules.

Worker
  - Adapters for native/RoadRunner/Swoole and worker-specific utilities (reset hooks, job processors).

Storage
  - Database, cache, and storage abstractions. Only expose typed repos/clients via container.

Auth/Validation/Serialization
  - Vertical modules that should be replaceable; they rely on container contracts and RequestContext only.
