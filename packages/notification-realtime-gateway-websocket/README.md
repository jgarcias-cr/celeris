# celeris/notification-realtime-gateway-websocket

Realtime gateway adapter package for publishing notification events to an external websocket service.

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

## Config

Uses host app `config/notifications.php` under `realtime`:

```php
'realtime' => [
    'enabled' => false,
    'endpoint' => '',
    'timeout_seconds' => 5,
    'service_id' => '',
    'service_secret' => '',
],
```

## Behavior

- `2xx` => success
- `429` and `5xx` => retryable failure
- other `4xx` => terminal failure
- network/transport errors => retryable failure
