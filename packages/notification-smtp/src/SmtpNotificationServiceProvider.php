<?php

declare(strict_types=1);

namespace Celeris\Notification\Smtp;

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\BootableServiceProviderInterface;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Container\ServiceRegistry;
use Celeris\Framework\Notification\NotificationManager;

/**
 * Registers the SMTP notification adapter into the host notification manager.
 */
final class SmtpNotificationServiceProvider implements BootableServiceProviderInterface
{
   public function register(ServiceRegistry $services): void
   {
      // No container bindings required for this adapter.
   }

   public function boot(ContainerInterface $container): void
   {
      if (!$container->has(ConfigRepository::class) || !$container->has(NotificationManager::class)) {
         return;
      }

      $config = $container->get(ConfigRepository::class);
      $manager = $container->get(NotificationManager::class);
      if (!$config instanceof ConfigRepository || !$manager instanceof NotificationManager) {
         return;
      }

      if (!self::toBool($config->get('notifications.channels.smtp.enabled', false))) {
         return;
      }

      $manager->registerChannel(new SmtpNotificationChannel(
         host: (string) $config->get('notifications.channels.smtp.host', '127.0.0.1'),
         port: (int) $config->get('notifications.channels.smtp.port', 587),
         encryption: (string) $config->get('notifications.channels.smtp.encryption', 'tls'),
         username: (string) $config->get('notifications.channels.smtp.username', ''),
         password: (string) $config->get('notifications.channels.smtp.password', ''),
         fromAddress: self::nullableString($config->get('notifications.channels.smtp.from_address')),
         fromName: self::nullableString($config->get('notifications.channels.smtp.from_name')),
         timeoutSeconds: (int) $config->get('notifications.channels.smtp.timeout_seconds', 10),
         ehloDomain: (string) $config->get('notifications.channels.smtp.ehlo_domain', 'localhost'),
      ), 'smtp');
   }

   private static function toBool(mixed $value): bool
   {
      if (is_bool($value)) {
         return $value;
      }

      if (is_int($value) || is_float($value)) {
         return $value !== 0;
      }

      if (is_string($value)) {
         $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
         return $parsed ?? false;
      }

      return false;
   }

   private static function nullableString(mixed $value): ?string
   {
      $clean = trim((string) $value);
      return $clean !== '' ? $clean : null;
   }
}
