<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Purpose: define the contract for notification channel interface behavior in the Notification subsystem.
 * How: declares typed method signatures that implementations must fulfill.
 * Used in framework: implemented by concrete notification services and resolved via dependency injection.
 */
interface NotificationChannelInterface
{
   /**
    * Handle name.
    *
    * @return string
    */
   public function name(): string;

   /**
    * Handle send.
    *
    * @param NotificationEnvelope $envelope
    * @return DeliveryResult
    */
   public function send(NotificationEnvelope $envelope): DeliveryResult;
}



