# Module Boundaries

This document reflects current core modules in `packages/framework/src`.

Kernel
  - Composition root and lifecycle orchestrator.
  - Wires container, providers, routing, middleware, security, notification manager, database/cache bootstrap, and response finalization.
  - Must not host domain/business use cases.

Runtime
  - Runtime adapters and worker loop integration surfaces (`FPM`, `Native`, `RoadRunner`, `Swoole` bridges).
  - Owns translation between runtime transport frames and framework `RuntimeRequest`.
  - Owns adapter start/reset/stop hooks and transport emission boundaries.

Http
  - Core request/response abstractions, headers/cookies/body primitives, request context, and PSR bridge helpers.
  - No transport event loop ownership; runtime transport belongs to `Runtime`.

Routing
  - Route definitions, grouping, metadata, attribute route loading, and OpenAPI generation.
  - Returns route metadata; handler execution is coordinated by kernel/pipeline layers.

Middleware
  - Request/response pipeline composition and middleware dispatch contracts.
  - Cross-cutting behavior should be implemented as middleware, not in route metadata.

Container
  - Service definitions, provider registration, lifetimes (`singleton/request/transient`), request-scope containers, and resolution safety.

Config
  - Environment/secrets/config snapshots and repository access.
  - Central source for runtime/package configuration values.

Security
  - Input/request guards, auth strategies, authorization, CSRF, rate limiting, security headers, and password hashing helpers.

Validation
  - Attribute-based validation contracts and runtime validator engine.

Serialization
  - DTO mapping and serializer pipeline (depends on `Validation` contracts/metadata).

Database
  - DBAL, connections, SQL dialects, migrations, ORM, and optional Active Record compatibility services.

Cache
  - Cache engine/bootstrap, stores (in-memory/redis), deterministic invalidation, HTTP cache finalization support.

Domain
  - Domain state primitives and domain event dispatcher contracts.

Notification
  - Core notification abstractions (`NotificationManager`, envelopes, channel contract, delivery result).
  - Channel transports/providers live in optional packages.

Distributed
  - Service auth, tracing, observability middleware/hooks, messaging abstractions, and distributed runtime helpers.

Tooling
  - CLI/web tooling, generators, architecture checks, and dependency graph builders.

View
  - Optional template renderer contracts/factories and adapters (PHP/Twig/Plates/Latte).

Current Core Module Dependency Map
  - Source of truth: `php packages/framework/bin/celeris graph --format=text`
  - `Cache -> Config, Http`
  - `Config -> (none)`
  - `Container -> Http`
  - `Database -> Config, Container, Domain`
  - `Distributed -> Database, Http, Middleware`
  - `Domain -> (none)`
  - `Http -> (none)`
  - `Kernel -> Cache, Config, Container, Database, Domain, Http, Middleware, Notification, Routing, Security, Serialization, Validation`
  - `Middleware -> Http`
  - `Notification -> Config`
  - `Routing -> (none)`
  - `Runtime -> Http, Kernel, Security`
  - `Security -> Config, Http`
  - `Serialization -> Validation`
  - `Tooling -> Container, Http, Routing`
  - `Validation -> (none)`
  - `View -> (none)`
