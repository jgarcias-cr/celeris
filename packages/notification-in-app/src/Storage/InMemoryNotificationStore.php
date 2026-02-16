<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp\Storage;

use Celeris\Notification\InApp\Contracts\NotificationStoreInterface;
use Celeris\Notification\InApp\InAppNotification;

final class InMemoryNotificationStore implements NotificationStoreInterface
{
    /** @var array<string, InAppNotification> */
    private array $items = [];

    public function save(InAppNotification $notification): string
    {
        $this->items[$notification->id()] = $notification;
        return $notification->id();
    }

    /**
     * @return array<int, InAppNotification>
     */
    public function listForUser(string $userId, int $limit = 50, bool $onlyUnread = false): array
    {
        $resolvedLimit = max(1, min(500, $limit));
        $filtered = [];

        foreach ($this->items as $notification) {
            if ($notification->userId() !== $userId) {
                continue;
            }
            if ($onlyUnread && $notification->status() !== 'unread') {
                continue;
            }
            $filtered[] = $notification;
        }

        usort(
            $filtered,
            static fn (InAppNotification $a, InAppNotification $b): int => $b->createdAtUnix() <=> $a->createdAtUnix()
        );

        return array_slice($filtered, 0, $resolvedLimit);
    }

    public function markRead(string $notificationId, ?float $readAtUnix = null): bool
    {
        $id = trim($notificationId);
        if ($id === '' || !isset($this->items[$id])) {
            return false;
        }

        $this->items[$id] = $this->items[$id]->withReadAt($readAtUnix ?? microtime(true));
        return true;
    }
}
