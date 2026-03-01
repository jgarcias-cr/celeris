# ADR 006 — Realtime user notification delivery (Phase 3)

Status: Proposed

Context
  - `ADR 005` established notification core contracts and optional transport adapters.
  - Current implementation supports synchronous channel dispatch and optional SMTP package integration.
  - Product requirements now include realtime app messages to final users (process started/finished, transaction approved/rejected, etc.).
  - A websocket service already exists in the system architecture (outside PHP runtime).
  - Realtime delivery must remain reliable under worker runtimes and process restarts.

Decision
  - Implement Phase 3 as a package-oriented architecture with strict boundaries between:
    - notification intent creation,
    - durable asynchronous dispatch,
    - websocket push delivery.
  - Introduce an `in_app` generic channel as the canonical source of truth for user-facing app notifications.
  - Use transactional outbox for async publication to websocket service; websocket push is a secondary realtime projection.
  - Keep `celeris/framework` transport-agnostic; all durable queue/gateway implementations live in optional packages.

Package boundaries
  - `celeris/framework` (existing core, minimal additions only if required)
    - Owns: `NotificationManager`, `NotificationChannelInterface`, `NotificationEnvelope`, `DeliveryResult`.
    - Must not own: provider SDK logic, durable queue drivers, websocket protocol adapters.
  - `celeris/notification-in-app` (new)
    - Owns: `InAppNotificationChannel`, persistence schema contract for user notifications, read/unread semantics.
    - Writes durable notification records in application DB.
    - Returns `DeliveryResult::delivered('in_app', <notification_id>)` when persisted.
  - `celeris/notification-outbox` (new)
    - Owns: outbox writer and outbox repository abstractions.
    - Persists outbox events in same DB transaction as business state and `in_app` notification record.
    - Provides idempotency key strategy and event status transitions (`pending`, `processing`, `sent`, `failed`, `dead_letter`).
  - `celeris/notification-dispatch-worker` (new)
    - Owns: polling/claiming outbox events, retry policy, backoff, dead-letter handling.
    - Publishes normalized realtime events to gateway adapter package.
  - `celeris/notification-realtime-gateway-websocket` (new)
    - Owns: outbound integration to existing websocket service (HTTP publish API, Redis pub/sub, or broker topic based on deployment).
    - Must implement authenticated service-to-service calls and idempotent publish semantics.

Canonical flow
  1. Application service calls `NotificationManager->send(...)` with intent (`type`) and channel (`in_app`).
  2. `InAppNotificationChannel` writes notification row (`notifications` table).
  3. Same transaction writes outbox row (`notification_outbox` table) referencing notification ID and target user ID.
  4. Dispatch worker claims pending outbox rows and publishes realtime payload to websocket gateway.
  5. Websocket service pushes message to user connections/rooms.
  6. Worker marks outbox row `sent` on acknowledgment, or retries/parks in dead-letter on repeated failure.
  7. Client UI treats websocket push as immediate hint and still reads from API for durable state.

Data contracts
  - Notification record (source of truth):
    - `id`, `user_id`, `type`, `title`, `body`, `data_json`, `status`, `created_at`, `read_at`.
  - Outbox record (delivery pipeline):
    - `id`, `event_name`, `aggregate_type`, `aggregate_id`, `payload_json`, `idempotency_key`,
      `attempt_count`, `next_attempt_at`, `status`, `last_error`, `created_at`, `processed_at`.
  - Realtime websocket event payload:
    - `event`: `notification.created`
    - `notification_id`
    - `user_id`
    - `type`
    - `title`
    - `body`
    - `data`
    - `occurred_at`

Configuration boundaries
  - Keep channel configuration in `config/notifications.php`.
  - Add async delivery configuration in new `config/realtime.php` (or `notifications.realtime.*` if single-file preference is kept):
    - worker batch size, lock timeout, max attempts, backoff policy.
    - websocket gateway endpoint/topic and auth credentials.
    - idempotency TTL and dead-letter policy.

Reliability and delivery semantics
  - Delivery guarantee from application to outbox: exactly-once per DB transaction boundary.
  - Delivery from outbox to websocket service: at-least-once.
  - Required safeguards:
    - idempotency key per notification event,
    - deterministic retry/backoff,
    - dead-letter queue/table after max attempts,
    - structured error metadata for operations.

Security model
  - Websocket gateway integration must use service authentication (HMAC/JWT/mTLS) with short-lived credentials.
  - Payloads must never include sensitive secrets or raw credentials.
  - User authorization remains in API: websocket layer only broadcasts already-authorized notification messages.

Observability requirements
  - Emit metrics per package boundary:
    - notifications persisted (`in_app`),
    - outbox enqueue rate and lag,
    - dispatch success/failure/retry/dead-letter counts,
    - websocket publish latency.
  - Include correlation fields (`notification_id`, `outbox_id`, `trace_id`) in logs/events.

Out of scope
  - Owning websocket server implementation inside Celeris.
  - Browser client SDK for websocket subscription.
  - Multi-region event replication guarantees.

Consequences
  - Realtime UX improves without sacrificing durability.
  - Notification state remains queryable even if websocket delivery fails.
  - Package boundaries keep framework core small and enable provider-specific evolution.
  - Operational complexity increases (worker process + outbox lifecycle), but is explicit and testable.

Rollout plan
  - Phase 3A:
    - deliver `celeris/notification-in-app` with DB persistence and API read model.
  - Phase 3B:
    - deliver `celeris/notification-outbox` and transaction integration.
  - Phase 3C:
    - deliver `celeris/notification-realtime-gateway-websocket` and `celeris/notification-dispatch-worker`.
  - Phase 3D:
    - publish production runbook (retry tuning, dead-letter replay, observability dashboards).

Implementation planning reference
  - Ticket-level milestones and acceptance criteria are defined in:
    - `docs/ADR/adr-006-phase-3-delivery-plan.md`
