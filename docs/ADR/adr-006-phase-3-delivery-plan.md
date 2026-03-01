# ADR 006 Delivery Plan — Phase 3 milestones and tickets

Status: Proposed

Context
  - `ADR 006` defines the target architecture for realtime end-user notifications.
  - This document translates that architecture into concrete build milestones and implementable tickets.

Scope
  - Covers package-level implementation for:
    - `celeris/notification-in-app`
    - `celeris/notification-outbox`
    - `celeris/notification-realtime-gateway-websocket`
    - `celeris/notification-dispatch-worker`
  - Assumes an external websocket service already exists.

Delivery principles
  - Persist-first, push-second.
  - Outbox is mandatory for realtime delivery.
  - At-least-once delivery to websocket gateway with idempotency safeguards.
  - No provider/websocket SDK logic in `celeris/framework` core.

Milestone map
  - M1 (`P3A`): `celeris/notification-in-app` (durable user notification store).
  - M2 (`P3B`): `celeris/notification-outbox` (transactional outbox + retry metadata).
  - M3 (`P3C`): `celeris/notification-realtime-gateway-websocket` (gateway adapter package).
  - M4 (`P3D`): `celeris/notification-dispatch-worker` (async dispatcher worker).
  - M5 (`P3E`): operational hardening and runbook publication.

Package dependency order
  - `celeris/framework` (existing base contracts)
  - `celeris/notification-outbox`
  - `celeris/notification-in-app` (integrates outbox writer)
  - `celeris/notification-realtime-gateway-websocket`
  - `celeris/notification-dispatch-worker` (depends on outbox + realtime gateway)

M1 — `celeris/notification-in-app`

Ticket `P3A-01` — Package skeleton and provider wiring
  - Deliverables:
    - package `composer.json`, namespace, bootstrap autoload helper.
    - `InAppNotificationServiceProvider`.
  - Acceptance criteria:
    - package installs in API/MVC stubs.
    - provider registers with `class_exists(...)` pattern.

Ticket `P3A-02` — In-app notification persistence schema and repository
  - Deliverables:
    - migration/table contract for `notifications`.
    - repository interface + default DBAL implementation.
  - Acceptance criteria:
    - can create/read notification rows by `user_id`.
    - migration is deterministic and repeatable.

Ticket `P3A-03` — `InAppNotificationChannel` implementation
  - Deliverables:
    - class implementing `NotificationChannelInterface`.
    - payload validation rules for in-app message shape.
  - Acceptance criteria:
    - `send(...)` persists record and returns `DeliveryResult::delivered('in_app', <id>)`.
    - invalid payload returns failed `DeliveryResult` without process crash.

Ticket `P3A-04` — Read model API support (stub examples)
  - Deliverables:
    - sample endpoints: list unread, list all, mark read.
  - Acceptance criteria:
    - user can fetch and mark notifications as read.
    - endpoints enforce auth context ownership (`user_id` scoping).

Ticket `P3A-05` — Tests and docs
  - Deliverables:
    - validation script or tests for channel + repository.
    - package README usage and payload contract.
  - Acceptance criteria:
    - tests cover happy path + invalid payload + read status transition.

M2 — `celeris/notification-outbox`

Ticket `P3B-01` — Outbox schema and repository contract
  - Deliverables:
    - migration/table contract for `notification_outbox`.
    - repository interface with `enqueue`, `claimBatch`, `markSent`, `markFailed`, `markDeadLetter`.
  - Acceptance criteria:
    - outbox events can be enqueued and queried by status.
    - status transitions are explicit and validated.

Ticket `P3B-02` — Transactional outbox writer
  - Deliverables:
    - outbox writer integrated with existing DB transaction boundaries.
  - Acceptance criteria:
    - business write + notification write + outbox write commit atomically.
    - rollback removes all three writes.

Ticket `P3B-03` — Idempotency and retry metadata
  - Deliverables:
    - idempotency key generator strategy.
    - fields: `attempt_count`, `next_attempt_at`, `last_error`.
  - Acceptance criteria:
    - duplicate publish attempts carry same idempotency key.
    - retry schedule updates metadata deterministically.

Ticket `P3B-04` — Operational query helpers
  - Deliverables:
    - methods for lag inspection and dead-letter listing.
  - Acceptance criteria:
    - operators can inspect pending age and failed messages without raw SQL.

Ticket `P3B-05` — Tests and docs
  - Deliverables:
    - integration tests for commit/rollback behavior.
  - Acceptance criteria:
    - tests prove atomicity and retry state transitions.

M3 — `celeris/notification-realtime-gateway-websocket`

Ticket `P3C-01` — Gateway client contract + implementation
  - Deliverables:
    - `RealtimeGatewayClientInterface`.
    - websocket gateway adapter (HTTP publish or configured transport).
  - Acceptance criteria:
    - adapter publishes normalized event payload with correlation IDs.

Ticket `P3C-02` — Service authentication
  - Deliverables:
    - signed requests using service auth strategy.
  - Acceptance criteria:
    - rejected auth from gateway is classified as non-success.
    - secrets loaded only from config/env/secrets providers.

Ticket `P3C-03` — Error classification for worker retry policy
  - Deliverables:
    - classify errors as retryable vs terminal.
  - Acceptance criteria:
    - 5xx/timeouts are retryable.
    - 4xx contract/auth errors become terminal (dead-letter path).

Ticket `P3C-04` — Tests and docs
  - Deliverables:
    - adapter tests with fake gateway responses.
  - Acceptance criteria:
    - tests cover success, retryable failure, terminal failure.

M4 — `celeris/notification-dispatch-worker`

Ticket `P3D-01` — Worker runtime loop
  - Deliverables:
    - command/runner to poll and claim outbox events in batches.
  - Acceptance criteria:
    - worker handles repeated loops with controlled backoff.
    - graceful shutdown does not corrupt claimed state.

Ticket `P3D-02` — Publish + ack workflow
  - Deliverables:
    - publish through `RealtimeGatewayClientInterface`.
    - mark sent on acknowledgment.
  - Acceptance criteria:
    - successful publish transitions event to `sent` with `processed_at`.

Ticket `P3D-03` — Retry and dead-letter handling
  - Deliverables:
    - max attempts, exponential or fixed backoff strategy.
  - Acceptance criteria:
    - failed retryable events reschedule via `next_attempt_at`.
    - exceeded attempts transition to `dead_letter`.

Ticket `P3D-04` — Concurrency control
  - Deliverables:
    - claim-lock strategy to prevent double processing in multi-worker deployments.
  - Acceptance criteria:
    - same outbox row is not processed concurrently by two workers.

Ticket `P3D-05` — Metrics and structured logs
  - Deliverables:
    - counters and timers: processed, success, retry, dead-letter, latency.
    - structured logging with `notification_id`, `outbox_id`, `trace_id`.
  - Acceptance criteria:
    - logs and metrics allow reconstruction of delivery lifecycle.

Ticket `P3D-06` — Tests and load checks
  - Deliverables:
    - integration tests using fake outbox and fake gateway.
    - load scenario for retry and backpressure.
  - Acceptance criteria:
    - worker remains stable under burst processing with deterministic retries.

M5 — `P3E` hardening and release readiness

Ticket `P3E-01` — Security review
  - Deliverables:
    - threat model for gateway publish path.
    - secret handling and rotation procedure.
  - Acceptance criteria:
    - no sensitive payload leakage in logs.

Ticket `P3E-02` — Runbook and operations guide
  - Deliverables:
    - production runbook for retries, dead-letter replay, and incident response.
  - Acceptance criteria:
    - on-call can diagnose and replay dead-letter messages from docs alone.

Ticket `P3E-03` — Manual and examples updates
  - Deliverables:
    - user manual section for in-app + realtime architecture.
    - sample API endpoints and frontend interaction notes.
  - Acceptance criteria:
    - developers can integrate end-to-end without reading source code.

P3E implementation artifacts
  - `P3E-01` security review:
    - `docs/security/notification-realtime-security-review.md`
  - `P3E-02` operations runbook:
    - `docs/runbooks/realtime-notification-operations.md`
  - `P3E-03` manual update:
    - `docs/user-manual.md` (`23.9 Step 9: In-app + realtime delivery end-to-end`)

Definition of done (Phase 3 complete)
  - `in_app` notifications persist durably and are queryable/read-markable.
  - outbox dispatch to websocket gateway works with retry and dead-letter handling.
  - worker runtime is operable with metrics, logs, and documented runbook.
  - package boundaries from `ADR 006` are preserved with no core transport coupling.
