# Project layout (suggested)

Root
  - `public/` — public document root, e.g. `index.php`, assets
  - `src/` — PHP source code, PSR-4 namespaces
    - `Kernel/` — composition root and lifecycle
    - `Http/` — Request/Response types, adapters
    - `Routing/` — router and route metadata
    - `Middleware/` — builtin middleware implementations
    - `Container/` — DI container, service providers
    - `Event/` — event bus and subscribers
    - `Worker/` — adapters for native/RoadRunner/Swoole
    - `Auth/`, `Validation/`, `Serializer/`, `Storage/` — cross-cutting modules
  - `config/` — environment and service configs (php config, container definitions)
  - `scripts/` — helper scripts
  - `tests/` — unit and integration tests
  - `docs/` — architecture docs and ADRs

Notes
  - Keep `src/` strictly typed; prefer small, focused classes and interfaces.
