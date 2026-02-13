<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Contracts;

/**
 * Contract for timing arbitrary application tasks.
 *
 * Implementations should execute the task callback and persist timing/
 * success information as task metrics.
 */
interface PulseRecorderInterface
{
    /**
     * @param callable(): mixed $task
     * @param array<string, scalar|null> $tags
     */
    public function measure(string $name, callable $task, array $tags = []): mixed;
}
