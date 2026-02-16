# celeris/notification-smtp

Official SMTP adapter package for Celeris notifications.

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

## Config

Uses host app `config/notifications.php` under `channels.smtp`.
