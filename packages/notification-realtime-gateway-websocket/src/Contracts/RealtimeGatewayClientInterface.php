<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway\Contracts;

use Celeris\Notification\RealtimeGateway\RealtimeEventMessage;
use Celeris\Notification\RealtimeGateway\RealtimePublishResult;

interface RealtimeGatewayClientInterface
{
    public function publish(RealtimeEventMessage $message): RealtimePublishResult;
}
