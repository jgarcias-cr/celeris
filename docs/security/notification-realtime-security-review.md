# Realtime Notification Security Review

Status: Proposed

Scope
- `celeris/notification-in-app`
- `celeris/notification-outbox`
- `celeris/notification-realtime-gateway-websocket`
- `celeris/notification-dispatch-worker`

## 1. Threat model

Assets
- User notification content (`title`, `body`, `data`).
- Outbox payloads and delivery metadata.
- Realtime gateway credentials (`service_id`, `service_secret`).
- Delivery workflow integrity (no forged/duplicated unauthorized sends).

Trust boundaries
1. API/MVC app process (trusted application runtime).
2. Database (trusted but compromise-impactful).
3. Dispatch worker process (trusted runtime with high privileges).
4. External websocket gateway service (trusted integration boundary).
5. Browser/mobile clients (untrusted network edge).

Primary attack scenarios
1. Forged publish requests to websocket gateway.
2. Replay of previously valid publish requests.
3. Secrets leakage via logs, crash traces, or repository commits.
4. Notification data exfiltration from logs/metrics.
5. Unauthorized client subscription to other users' channels/rooms.
6. Poisoned outbox payload causing repeated worker failures.
7. Denial of service through uncontrolled retry loops.

## 2. Current controls (implemented)

From current implementation:
1. Realtime gateway adapter supports signed service headers (`x-celeris-service-id`, timestamp, signature).
2. Retryable vs terminal failure classification is explicit (`429/5xx` retryable, most `4xx` terminal).
3. Outbox processing supports max attempts and dead-letter transitions.
4. Notification channel/provider boundaries are explicit and package-isolated.

## 3. Required controls for production

Authentication and integrity
1. Enforce HMAC signature validation on websocket gateway side.
2. Enforce strict timestamp skew window (recommended: <= 120 seconds).
3. Include idempotency key in gateway dedupe policy.
4. Reject unsigned requests when gateway auth is configured.

Replay protection
1. Gateway must reject duplicate `(service_id, idempotency_key)` within replay window.
2. Keep idempotency records at least as long as outbox retry horizon.

Authorization model
1. API remains source of authorization.
2. Realtime gateway must only route by server-provided `user_id`; never trust client-provided target identity.
3. Client subscription auth token must include user identity and short expiry.

Data minimization
1. Never include secrets or credentials in outbox payload.
2. Keep payloads limited to notification metadata needed by clients.
3. Put sensitive business details behind follow-up API fetch where possible.

Logging and observability hygiene
1. Redact `service_secret`, signatures, auth headers, and full payloads by default.
2. Log identifiers only: `notification_id`, `outbox_id`, `trace_id`, status.
3. Restrict debug payload logging to controlled non-production environments.

Operational safeguards
1. Dead-letter queue/table must be monitored and alertable.
2. Retry backoff must have bounded maximum and jitter in production.
3. Worker concurrency must use claim-lock semantics and lock expiry.

## 4. Secret handling and rotation procedure

Storage rules
1. Store realtime credentials in environment/secrets store, not in source control.
2. Do not print secrets in startup logs.
3. Restrict runtime access to secrets to API/worker processes only.

Rotation playbook (no downtime)
1. Create new credential pair (`service_id_new`, `service_secret_new`) in gateway.
2. Deploy gateway accepting both old and new credentials.
3. Deploy app/worker with new credentials.
4. Verify successful publishes and zero auth failures.
5. Revoke old credential pair.

Emergency rotation
1. Disable realtime publishing (`NOTIFICATIONS_REALTIME_ENABLED=false`) if abuse is ongoing.
2. Rotate credentials immediately.
3. Re-enable publishing after successful verification.

## 5. Security acceptance checklist (P3E-01)

1. Gateway rejects unsigned/invalid signatures.
2. Timestamp skew and replay checks are enforced.
3. Secrets are never logged in plaintext.
4. Outbox and worker logs do not expose sensitive payload content by default.
5. Dead-letter and retry alerts are configured.
6. Credential rotation run is documented and rehearsed.

## 6. Residual risks

1. Compromised gateway service can still emit unauthorized pushes.
2. Sensitive payload fields accidentally placed in notification `data` remain an app-layer risk.
3. Multi-region ordering and duplication behavior is deployment-dependent and out of current scope.
