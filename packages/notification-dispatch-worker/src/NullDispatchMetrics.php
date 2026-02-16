<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

use Celeris\Notification\DispatchWorker\Contracts\DispatchMetricsInterface;

final class NullDispatchMetrics implements DispatchMetricsInterface
{
    public function increment(string $metric, int $value = 1, array $labels = []): void
    {
    }

    public function observe(string $metric, float $value, array $labels = []): void
    {
    }
}
