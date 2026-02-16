<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;

final class NullRealtimeGatewayClient implements RealtimeGatewayClientInterface
{
    public function publish(RealtimeEventMessage $message): RealtimePublishResult
    {
        return RealtimePublishResult::terminalFailure(
            'Realtime gateway publishing is disabled.',
            null,
            ['disabled' => true],
        );
    }
}
