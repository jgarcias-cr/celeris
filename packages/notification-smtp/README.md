# celeris/notification-smtp

Official SMTP adapter package for Celeris notifications.

## Purpose

This package provides the SMTP notification channel for email delivery.
It registers an SMTP channel in `NotificationManager` and sends RFC 5322/MIME messages over SMTP with optional auth and TLS.

## Install

```bash
composer require celeris/notification-smtp
```

## Register provider

```php
if (class_exists(\Celeris\Notification\Smtp\SmtpNotificationServiceProvider::class)) {
    $kernel->registerProvider(new \Celeris\Notification\Smtp\SmtpNotificationServiceProvider());
}
```

## Configure In `.env`

In your API or MVC app, SMTP config is read from `config/notifications.php` under `notifications.channels.smtp.*`:

```dotenv
NOTIFICATIONS_SMTP_ENABLED=true
NOTIFICATIONS_SMTP_HOST=127.0.0.1
NOTIFICATIONS_SMTP_PORT=587
NOTIFICATIONS_SMTP_USERNAME=
NOTIFICATIONS_SMTP_PASSWORD=
NOTIFICATIONS_SMTP_ENCRYPTION=tls
NOTIFICATIONS_SMTP_TIMEOUT_SECONDS=10
NOTIFICATIONS_SMTP_EHLO_DOMAIN=localhost
NOTIFICATIONS_FROM_ADDRESS=no-reply@example.com
NOTIFICATIONS_FROM_NAME=Celeris
```

Configuration notes:

- `NOTIFICATIONS_SMTP_ENABLED=false` prevents the channel from being registered.
- `NOTIFICATIONS_SMTP_ENCRYPTION` supports `tls`, `starttls`, `ssl`, and `none`.
- A sender address is required either in envelope email message or via `NOTIFICATIONS_FROM_ADDRESS`.

## Usage notes

The SMTP channel delivers only envelopes that include an email message.
It fails fast when recipient, subject, or body are missing.

## When To Use It

Use this package when:

- Dependency specification: requires `celeris/framework` only (no additional native Celeris package dependency).
- You need first-party email delivery via SMTP.
- You want to keep email transport inside Celeris notification channels.
- You need support for authenticated SMTP relays.

You may skip it when:

- You use a different mail transport/provider SDK.
- Your app does not send email notifications.
