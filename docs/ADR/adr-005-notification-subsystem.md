# ADR 005 — Notification subsystem

Status: Accepted

Context
  - Both API and MVC apps need outbound notifications (email first, then SMS/push/webhook).
  - Celeris currently has no built-in notification transport; teams must wire providers ad hoc in app code.
  - We want one framework-level developer experience without forcing all apps to install SMTP/provider dependencies.

Decision
  - Introduce a framework-level Notification subsystem in core, focused on contracts + orchestration, with no mandatory external transport dependency.
  - Core ships:
    - `NotificationManager` service (single entrypoint for sending).
    - `NotificationChannelInterface` contract.
    - Message value objects (`EmailMessage`, `NotificationEnvelope`).
    - Result model (`DeliveryResult`) for success/failure metadata.
    - `NullNotificationChannel` as safe default.
  - Transport adapters are optional packages (for example SMTP, SES, Mailgun), installed only when needed.
  - Official SMTP adapter package is `celeris/notification-smtp`, registered via service provider.
  - Registration is provider-driven (same model as other Celeris services), and channel mapping is config-based.
  - Add shared config file shape for both stubs (`config/notifications.php`) with environment-driven values.

Proposed config shape
  - `default_channel` (e.g. `smtp`, `null`)
  - `channels.smtp` settings (host, port, username, password, encryption, from address/name)
  - `channels.null` enabled by default for local/test fallbacks
  - Optional retry policy (`max_attempts`, `backoff_ms`) for transient transport failures

Stub integration
  - API stub:
    - include `config/notifications.php`
    - register `NotificationManager` + default channel in `AppServiceProvider`
    - enable easy usage from services (e.g. auth OTP, account alerts)
  - MVC stub:
    - same shared config and service registration
    - enable usage from controllers/services (e.g. contact form notifications, account events)
  - Both stubs keep working with `NullNotificationChannel` even when no mail transport is configured.

Out of scope (initial phase)
  - Framework-owned template engines for email bodies.
  - Queue/worker delivery orchestration in notification core.
  - Provider-specific advanced features (webhook event sync, provider tags/campaigns).

Consequences
  - Consistent notification API across API and MVC projects.
  - Core remains lightweight and transport-agnostic.
  - Teams can start with `null` channel, then add SMTP/provider packages without rewriting app services.
  - Additional implementation packages and docs are required to complete end-to-end email delivery.

Rollout plan
  - Phase 1: add core contracts + `NotificationManager` + `NullNotificationChannel`.
  - Phase 2: add official SMTP adapter package and stub defaults.
    - Implemented as `celeris/notification-smtp` provider-driven channel registration.
  - Phase 3: add optional provider adapters and delivery retry guidance.
    - Detailed by `ADR 006` for realtime end-user notifications with websocket service integration.
