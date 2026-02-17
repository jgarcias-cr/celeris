# celeris/notification-realtime-gateway-websocket

Realtime gateway adapter package for publishing notification events to an external websocket service.

## Purpose

This package provides a realtime gateway client used to publish notification events over HTTP to an external websocket gateway.
It classifies publish outcomes so callers can decide retry vs terminal failure.

## Install

```bash
composer require celeris/notification-realtime-gateway-websocket
```

## Register provider

```php
if (class_exists(\Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\RealtimeGateway\RealtimeGatewayServiceProvider());
}
```

## Configure In `.env`

In your API or MVC app, realtime gateway config is read from `config/notifications.php` under `notifications.realtime.*`:

```dotenv
NOTIFICATIONS_REALTIME_ENABLED=true
NOTIFICATIONS_REALTIME_ENDPOINT=
NOTIFICATIONS_REALTIME_TIMEOUT_SECONDS=5
NOTIFICATIONS_REALTIME_SERVICE_ID=
NOTIFICATIONS_REALTIME_SERVICE_SECRET=
```

Configuration notes:

- `NOTIFICATIONS_REALTIME_ENABLED=false` binds a null client that does not publish.
- `NOTIFICATIONS_REALTIME_SERVICE_ID` and `NOTIFICATIONS_REALTIME_SERVICE_SECRET` are optional; when both are set, request signatures are added.
- `NOTIFICATIONS_REALTIME_ENDPOINT` must be set for successful publish calls.

## Behavior

- `2xx` => success
- `429` and `5xx` => retryable failure
- other `4xx` => terminal failure
- network/transport errors => retryable failure

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework` only (no additional native Celeris package dependency).
- You deliver user-facing realtime notifications through an external websocket gateway.
- You want a uniform publish client with retry/terminal classification.
- You want optional HMAC-style service authentication headers.

You may skip it when:

- You do not have a realtime gateway endpoint.
- Realtime delivery is handled by a different transport in your app.
