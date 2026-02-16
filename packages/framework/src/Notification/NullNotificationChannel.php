<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Purpose: implement null notification channel behavior for the Notification subsystem.
 * How: encapsulates its responsibilities behind explicit methods and typed dependencies.
 * Used in framework: invoked by notification components when null notification channel functionality is required.
 */
final class NullNotificationChannel implements NotificationChannelInterface
{
   public function __construct(private string $channelName = 'null')
   {
      $clean = trim($this->channelName);
      $this->channelName = $clean !== '' ? $clean : 'null';
   }

   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string
   {
      return $this->channelName;
   }

   /**
    * Handle send.
    *
    * @param NotificationEnvelope $envelope
    * @return DeliveryResult
    */
   public function send(NotificationEnvelope $envelope): DeliveryResult
   {
      $metadata = $envelope->metadata();
      $metadata['discarded'] = true;
      $metadata['type'] = $envelope->type();

      return DeliveryResult::delivered($this->channelName, null, $metadata);
   }
}



