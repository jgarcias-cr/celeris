# celeris/notification-in-app

Official in-app notification adapter package for Celeris.

## Purpose

This package provides an in-app notification channel backed by storage.
It validates notification payloads, persists notifications per user, and supports listing unread/read state.

## Install

```bash
composer require celeris/notification-in-app
```

## Register provider

```php
if (class_exists(\Celeris\Notification\InApp\InAppNotificationServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\InApp\InAppNotificationServiceProvider());
}
```

## Configure In `.env`

In your API or MVC app, in-app channel config is read from `config/notifications.php` under `notifications.channels.in_app.*`:

```dotenv
NOTIFICATIONS_IN_APP_ENABLED=true
NOTIFICATIONS_IN_APP_CONNECTION=
NOTIFICATIONS_IN_APP_TABLE=app_notifications
NOTIFICATIONS_IN_APP_AUTO_CREATE_TABLE=false
```

Configuration notes:

- `NOTIFICATIONS_IN_APP_ENABLED=false` prevents the channel from being registered.
- `NOTIFICATIONS_IN_APP_CONNECTION` falls back to `database.default` when empty.
- `NOTIFICATIONS_IN_APP_AUTO_CREATE_TABLE=true` auto-creates the table for SQLite, PostgreSQL, MySQL/MariaDB, and SQL Server.

## Payload shape

`InAppNotificationChannel` expects envelope payload as array:

```php
[
    'user_id' => '42',
    'title' => 'Transfer completed',
    'body' => 'Your transfer #A-1001 was approved.',
    'data' => ['transaction_id' => 'A-1001'],
]
```

Required fields:

- `user_id` is required.
- At least one of `title` or `body` is required.

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework` only (no additional native Celeris package dependency).
- You need a first-party in-app inbox/notification feed.
- You want notifications persisted and queryable per user.
- You need read/unread state transitions.

You may skip it when:

- You only use external channels (email/SMS/realtime) and do not store in-app notifications.
