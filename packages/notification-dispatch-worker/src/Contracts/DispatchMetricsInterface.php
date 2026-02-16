<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker\Contracts;

interface DispatchMetricsInterface
{
    /** @param array<string, scalar> $labels */
    public function increment(string $metric, int $value = 1, array $labels = []): void;

    /** @param array<string, scalar> $labels */
    public function observe(string $metric, float $value, array $labels = []): void;
}
