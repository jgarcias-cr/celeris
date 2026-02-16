<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp\Contracts;

use Celeris\Notification\InApp\InAppNotification;

interface NotificationStoreInterface
{
    public function save(InAppNotification $notification): string;

    /**
     * @return array<int, InAppNotification>
     */
    public function listForUser(string $userId, int $limit = 50, bool $onlyUnread = false): array;

    public function markRead(string $notificationId, ?float $readAtUnix = null): bool;
}
