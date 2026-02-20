# Project Layout (Current Monorepo)

Root
  - `packages/` — all first-party packages (framework, stubs, optional integrations)
  - `docs/` — architecture docs, ADRs, runbooks, security notes, examples
  - `docker/` — local runtime/container assets
  - `composer.json` — workspace package mapping
  - `docker-compose.yml` — local orchestration

Packages
  - `packages/framework` (`celeris/framework`) — core framework package
  - `packages/api-stub` (`celeris/api`) — API reference/starter app
  - `packages/mvc-stub` (`celeris/mvc`) — MVC reference/starter app
  - `packages/notification-smtp` — SMTP notification channel package
  - `packages/notification-in-app` — durable in-app notification package
  - `packages/notification-outbox` — transactional outbox package
  - `packages/notification-realtime-gateway-websocket` — realtime gateway client package
  - `packages/notification-dispatch-worker` — outbox dispatch worker package
  - `packages/queue-manager` — in-app queue manager package
  - `packages/pulse-sample-package` — sample observability/metrics package

Framework Core Layout (`packages/framework`)
  - `src/` — framework source modules
    - `Kernel/`, `Runtime/`, `Http/`, `Routing/`, `Middleware/`, `Container/`, `Config/`
    - `Security/`, `Validation/`, `Serialization/`, `Database/`, `Cache/`
    - `Domain/`, `Notification/`, `Distributed/`, `Tooling/`, `View/`
  - `tests/` — framework validation tests
  - `bin/celeris` — tooling CLI entrypoint

Application Stub Layout (`packages/api-stub`, `packages/mvc-stub`)
  - `app/` — app services/controllers/models
  - `config/` — app configuration
  - `public/` — front controller/static assets
  - `bin/` — operational scripts (for example notification worker/replay scripts)
  - `.env.example` — env template

Notes
  - This repository is package-oriented; root-level `src/` and `public/` are not the primary structure.
  - Keep package boundaries explicit: core abstractions in `framework`, integrations in optional packages.
