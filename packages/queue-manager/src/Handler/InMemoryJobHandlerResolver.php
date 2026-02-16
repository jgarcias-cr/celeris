<?php

declare(strict_types=1);

namespace Celeris\QueueManager\Handler;

use Celeris\QueueManager\Contracts\JobHandlerResolverInterface;

final class InMemoryJobHandlerResolver implements JobHandlerResolverInterface
{
    /** @var array<string, callable(array<string, scalar|array<int|string, scalar>|null>, \Celeris\QueueManager\QueueJob): mixed> */
    private array $handlers = [];

    /**
     * @param callable(array<string, scalar|array<int|string, scalar>|null>, \Celeris\QueueManager\QueueJob): mixed $handler
     */
    public function register(string $taskName, callable $handler): self
    {
        $resolved = trim($taskName);
        if ($resolved === '') {
            throw new \InvalidArgumentException('Task name cannot be empty when registering queue handler.');
        }

        $this->handlers[$resolved] = $handler;
        return $this;
    }

    public function resolve(string $taskName): ?callable
    {
        $resolved = trim($taskName);
        if ($resolved === '') {
            return null;
        }

        return $this->handlers[$resolved] ?? null;
    }

    public function has(string $taskName): bool
    {
        return $this->resolve($taskName) !== null;
    }

    /** @return array<int, string> */
    public function taskNames(): array
    {
        $names = array_keys($this->handlers);
        sort($names);
        return $names;
    }
}
