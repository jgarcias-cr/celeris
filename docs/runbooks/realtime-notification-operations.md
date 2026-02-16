# Realtime Notification Operations Runbook

Status: Draft (`P3E-02`)

Scope
- `celeris/notification-in-app`
- `celeris/notification-outbox`
- `celeris/notification-realtime-gateway-websocket`
- `celeris/notification-dispatch-worker`

Related security review
- `docs/security/notification-realtime-security-review.md`

## 1. Required configuration

Minimum environment flags:

```env
NOTIFICATIONS_IN_APP_ENABLED=true
NOTIFICATIONS_OUTBOX_ENABLED=true
NOTIFICATIONS_REALTIME_ENABLED=true
NOTIFICATIONS_REALTIME_ENDPOINT=http://websocket-gateway.internal/publish
NOTIFICATIONS_REALTIME_SERVICE_ID=notification-service
NOTIFICATIONS_REALTIME_SERVICE_SECRET=replace-me
NOTIFICATIONS_DISPATCH_WORKER_ENABLED=true

NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS=5
NOTIFICATIONS_OUTBOX_BACKOFF_MS=500
NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE=100
NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS=30
NOTIFICATIONS_DISPATCH_WORKER_IDLE_SLEEP_MS=250
```

Provider registration must be enabled in bootstrap:
- `InAppNotificationServiceProvider`
- `OutboxServiceProvider`
- `RealtimeGatewayServiceProvider`
- `NotificationDispatchWorkerServiceProvider`

## 2. Startup checklist

1. Deploy API/MVC app code and restart app processes.
2. Confirm notification tables exist (`app_notifications`, `notification_outbox`) or set `*_AUTO_CREATE_TABLE=true` for bootstrap provisioning.
3. Verify realtime gateway endpoint is reachable from app/worker network.
4. Start dispatch worker process (stub scripts are included).
5. Send one test in-app notification and verify:
   - one `app_notifications` row exists,
   - one outbox row is created then transitions to `sent`,
   - websocket client receives event.

Dispatch worker scripts:
- API stub: `packages/api-stub/bin/notifications-dispatch-worker.php`
- MVC stub: `packages/mvc-stub/bin/notifications-dispatch-worker.php`

```bash
# one pass
php packages/api-stub/bin/notifications-dispatch-worker.php --once

# bounded loop
php packages/api-stub/bin/notifications-dispatch-worker.php --max-loops=100
```

## 3. Health checks

One-pass health probe:
- run `php packages/api-stub/bin/notifications-dispatch-worker.php --once`
- inspect report fields:
  - `claimed`
  - `published`
  - `retry_scheduled`
  - `dead_lettered`
  - `terminal_failed`
  - `errors`

Expected behavior:
1. Idle system: `claimed=0` consistently.
2. Under load: `published` increases while `pendingLagSeconds()` remains low.
3. Persistent `retry_scheduled` or `dead_lettered` growth requires incident handling.

Recommended metrics from worker package:
- `notifications.dispatch.claimed`
- `notifications.dispatch.published`
- `notifications.dispatch.retry_scheduled`
- `notifications.dispatch.dead_lettered`
- `notifications.dispatch.terminal_failed`
- `notifications.dispatch.errors`
- `notifications.dispatch.duration_ms`

## 4. Incident response playbooks

### 4.1 Backlog growing (`pending`/`retry` increasing)

Checks:
1. Verify worker process is running and `NOTIFICATIONS_DISPATCH_WORKER_ENABLED=true`.
2. Check gateway latency/timeouts and network egress.
3. Inspect outbox lag via `pendingLagSeconds()` and dead-letter counts.

Actions:
1. Scale worker replicas.
2. Reduce gateway instability first; then tune `claim_batch_size` and `idle_sleep_ms`.
3. Increase `max_attempts` only if failures are confirmed transient.

### 4.2 Realtime auth failures (`401`/`403`)

Checks:
1. Validate `NOTIFICATIONS_REALTIME_SERVICE_ID` and `NOTIFICATIONS_REALTIME_SERVICE_SECRET`.
2. Confirm gateway signature verification clock skew policy (recommended <= 120 seconds).
3. Review logs for terminal failures from gateway classification.

Actions:
1. Rotate credentials using the procedure in `docs/security/notification-realtime-security-review.md`.
2. If abuse is suspected, temporarily disable realtime publish (`NOTIFICATIONS_REALTIME_ENABLED=false`) while keeping in-app persistence enabled.

### 4.3 Mapping dead-letters (`missing user_id/event`)

Checks:
1. Review dead-letter `last_error`.
2. Confirm outbox payload includes `user_id` (or `userId`/`recipient_id`) and non-empty event name.

Actions:
1. Fix payload generation in application service.
2. Replay only affected dead-letter rows after fix.

## 5. Dead-letter replay procedure

Celeris currently has no dedicated `replay()` API in outbox repository. Replay is performed by re-enqueueing a new outbox row from dead-letter payload.

Replay scripts:
- API stub: `packages/api-stub/bin/notifications-replay-dead-letter.php`
- MVC stub: `packages/mvc-stub/bin/notifications-replay-dead-letter.php`

```bash
# inspect dead-letters only
php packages/api-stub/bin/notifications-replay-dead-letter.php --dry-run --limit=50

# replay one dead-letter
php packages/api-stub/bin/notifications-replay-dead-letter.php --id=<dead_letter_id>
```

Post-replay verification:
1. Run one dispatch pass (`php packages/api-stub/bin/notifications-dispatch-worker.php --once`).
2. Confirm new message transitions to `sent`.
3. Keep original dead-letter row for audit trail.

## 6. Operational guardrails

1. Never log `service_secret` or auth signatures.
2. Keep notification payload minimal; large sensitive content should be fetched through authenticated API.
3. Alert on dead-letter count > 0 for sustained periods.
4. Keep worker and gateway clocks synchronized.

## 7. Troubleshooting matrix

1. Symptom: `Composer detected issues in your platform` when running scripts.
   - Check: `php -v`.
   - Cause: runtime is below required PHP version (`>= 8.4`).
   - Action: run scripts with PHP 8.4+ and reinstall dependencies with that runtime.
2. Symptom: `OutboxDispatchWorker class is unavailable`.
   - Check: package install in app (`composer show celeris/notification-dispatch-worker`).
   - Cause: dispatch worker package missing.
   - Action: install package and ensure `NotificationDispatchWorkerServiceProvider` is registered.
3. Symptom: replay script warning `notifications.outbox.enabled=false`.
   - Check: `NOTIFICATIONS_OUTBOX_ENABLED` in `.env`.
   - Cause: outbox disabled (in-memory repository fallback).
   - Action: set `NOTIFICATIONS_OUTBOX_ENABLED=true`, configure outbox connection/table, rerun.
4. Symptom: repeated terminal failures (`401`/`403`) in dispatch reports.
   - Check: `NOTIFICATIONS_REALTIME_SERVICE_ID`, `NOTIFICATIONS_REALTIME_SERVICE_SECRET`, gateway auth logs.
   - Cause: invalid service credentials or signature validation mismatch.
   - Action: rotate credentials and verify gateway timestamp skew policy.
5. Symptom: dead-letters with mapping failure (`missing user_id/event`).
   - Check: outbox payload content for `user_id` and event name.
   - Cause: producer did not populate required payload fields.
   - Action: fix producer payload contract and replay affected dead-letter rows.
