<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

use Celeris\Framework\Config\ConfigRepository;
use InvalidArgumentException;

/**
 * Purpose: orchestrate notification manager workflows within Notification.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by notification components when notification manager functionality is required.
 */
final class NotificationManager
{
   /** @var array<string, NotificationChannelInterface> */
   private array $channels = [];
   private string $defaultChannel;

   /**
    * @param array<string, NotificationChannelInterface>|array<int, NotificationChannelInterface> $channels
    */
   public function __construct(array $channels = [], string $defaultChannel = 'null')
   {
      foreach ($channels as $name => $channel) {
         if (!$channel instanceof NotificationChannelInterface) {
            continue;
         }

         if (is_string($name)) {
            $this->registerChannel($channel, $name);
            continue;
         }

         $this->registerChannel($channel);
      }

      if (!isset($this->channels['null'])) {
         $this->channels['null'] = new NullNotificationChannel('null');
      }

      $resolvedDefault = self::normalizeChannelName($defaultChannel);
      $this->defaultChannel = $resolvedDefault !== '' ? $resolvedDefault : 'null';
   }

   /**
    * Create an instance from config.
    *
    * @param ConfigRepository $config
    * @return self
    */
   public static function fromConfig(ConfigRepository $config): self
   {
      $defaultChannel = (string) $config->get('notifications.default_channel', 'null');
      $nullEnabled = (bool) $config->get('notifications.channels.null.enabled', true);

      $channels = [];
      if ($nullEnabled || self::normalizeChannelName($defaultChannel) === 'null') {
         $channels['null'] = new NullNotificationChannel('null');
      }

      return new self($channels, $defaultChannel);
   }

   /**
    * Handle register channel.
    *
    * @param NotificationChannelInterface $channel
    * @param ?string $name
    * @return void
    */
   public function registerChannel(NotificationChannelInterface $channel, ?string $name = null): void
   {
      $resolved = self::normalizeChannelName($name ?? $channel->name());
      if ($resolved === '') {
         throw new InvalidArgumentException('Notification channel name cannot be empty.');
      }

      $this->channels[$resolved] = $channel;
   }

   /**
    * @return array<int, string>
    */
   public function channels(): array
   {
      $names = array_keys($this->channels);
      sort($names);
      return $names;
   }

   /**
    * Determine whether has channel.
    *
    * @param string $name
    * @return bool
    */
   public function hasChannel(string $name): bool
   {
      return isset($this->channels[self::normalizeChannelName($name)]);
   }

   /**
    * Handle send.
    *
    * @param NotificationEnvelope $envelope
    * @param ?string $channel
    * @return DeliveryResult
    */
   public function send(NotificationEnvelope $envelope, ?string $channel = null): DeliveryResult
   {
      $resolvedName = $this->resolveChannelName($envelope, $channel);
      $resolvedChannel = $this->channels[$resolvedName] ?? null;
      if (!$resolvedChannel instanceof NotificationChannelInterface) {
         return DeliveryResult::failed($resolvedName, 'Notification channel is not configured.');
      }

      return $resolvedChannel->send($envelope->withChannel($resolvedName));
   }

   /**
    * @param array<string, mixed> $metadata
    */
   public function sendEmail(EmailMessage $message, ?string $channel = null, array $metadata = []): DeliveryResult
   {
      return $this->send(NotificationEnvelope::email($message, $channel, $metadata), $channel);
   }

   /**
    * Handle default channel.
    *
    * @return string
    */
   public function defaultChannel(): string
   {
      return $this->defaultChannel;
   }

   /**
    * @param NotificationEnvelope $envelope
    * @param ?string $override
    * @return string
    */
   private function resolveChannelName(NotificationEnvelope $envelope, ?string $override = null): string
   {
      $candidate = self::normalizeChannelName($override ?? '');
      if ($candidate !== '') {
         return $candidate;
      }

      $candidate = self::normalizeChannelName($envelope->channel() ?? '');
      if ($candidate !== '') {
         return $candidate;
      }

      return $this->defaultChannel;
   }

   private static function normalizeChannelName(string $name): string
   {
      return strtolower(trim($name));
   }
}

