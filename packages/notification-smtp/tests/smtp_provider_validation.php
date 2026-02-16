<?php

declare(strict_types=1);

require __DIR__ . '/../../framework/src/bootstrap.php';
require __DIR__ . '/../src/bootstrap.php';

use Celeris\Framework\Config\ConfigRepository;
use Celeris\Framework\Container\ContainerInterface;
use Celeris\Framework\Notification\NotificationManager;
use Celeris\Notification\Smtp\SmtpNotificationServiceProvider;

final class TestContainer implements ContainerInterface
{
   /** @param array<string, mixed> $services */
   public function __construct(private array $services)
   {
   }

   public function has(string $id): bool
   {
      return array_key_exists($id, $this->services);
   }

   public function get(string $id): mixed
   {
      return $this->services[$id] ?? null;
   }
}

function assertTrue(string $label, bool $condition): void
{
   if (!$condition) {
      throw new RuntimeException($label);
   }
}

function testProviderRegistersSmtpChannelWhenEnabled(): void
{
   $config = new ConfigRepository([
      'notifications' => [
         'default_channel' => 'null',
         'channels' => [
            'null' => ['enabled' => true],
            'smtp' => [
               'enabled' => true,
               'host' => '127.0.0.1',
               'port' => 587,
               'encryption' => 'tls',
               'username' => '',
               'password' => '',
               'from_address' => 'no-reply@example.com',
               'from_name' => 'Celeris',
               'timeout_seconds' => 10,
               'ehlo_domain' => 'localhost',
            ],
         ],
      ],
   ]);

   $manager = NotificationManager::fromConfig($config);
   $provider = new SmtpNotificationServiceProvider();

   $provider->boot(new TestContainer([
      ConfigRepository::class => $config,
      NotificationManager::class => $manager,
   ]));

   assertTrue('smtp channel should be registered when smtp is enabled', $manager->hasChannel('smtp'));
}

function testProviderSkipsSmtpChannelWhenDisabled(): void
{
   $config = new ConfigRepository([
      'notifications' => [
         'default_channel' => 'null',
         'channels' => [
            'null' => ['enabled' => true],
            'smtp' => ['enabled' => false],
         ],
      ],
   ]);

   $manager = NotificationManager::fromConfig($config);
   $provider = new SmtpNotificationServiceProvider();

   $provider->boot(new TestContainer([
      ConfigRepository::class => $config,
      NotificationManager::class => $manager,
   ]));

   assertTrue('smtp channel should not be registered when smtp is disabled', !$manager->hasChannel('smtp'));
}

testProviderRegistersSmtpChannelWhenEnabled();
testProviderSkipsSmtpChannelWhenDisabled();

echo "smtp_provider_validation: ok\n";
