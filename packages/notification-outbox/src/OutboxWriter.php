<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;

/**
 * Coordinates transactional writes that include outbox enqueue operations.
 */
final class OutboxWriter
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly OutboxRepositoryInterface $repository,
    ) {
    }

    /**
     * @param array<int, OutboxMessage> $messages
     */
    public function transactional(callable $callback, array $messages = []): mixed
    {
        return $this->connection->transactional(function (ConnectionInterface $conn) use ($callback, $messages): mixed {
            $result = $callback($conn);

            foreach ($messages as $message) {
                $this->repository->enqueue($message);
            }

            return $result;
        });
    }

    public function enqueue(OutboxMessage $message): string
    {
        return $this->repository->enqueue($message);
    }
}
