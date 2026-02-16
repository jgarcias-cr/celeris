<?php

declare(strict_types=1);

namespace Celeris\QueueManager\Contracts;

interface JobHandlerResolverInterface
{
    /**
     * @return callable(array<string, scalar|array<int|string, scalar>|null>, \Celeris\QueueManager\QueueJob): mixed|null
     */
    public function resolve(string $taskName): ?callable;
}
