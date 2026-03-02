<?php

declare(strict_types=1);

namespace Celeris\Framework\Notification;

/**
 * Define the contract for notification channel interface behavior in the Notification subsystem.
 *
 * It provides focused behavior for this type within the framework.
 * In practice, it is used by adjacent modules through explicit dependencies.
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



