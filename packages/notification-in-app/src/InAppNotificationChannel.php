<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp;

use Celeris\Framework\Notification\DeliveryResult;
use Celeris\Framework\Notification\NotificationChannelInterface;
use Celeris\Framework\Notification\NotificationEnvelope;
use Celeris\Notification\InApp\Contracts\NotificationStoreInterface;
use Throwable;

final class InAppNotificationChannel implements NotificationChannelInterface
{
    public function __construct(
        private NotificationStoreInterface $store,
        private string $channelName = 'in_app',
    ) {
        $this->channelName = trim($this->channelName) !== '' ? trim($this->channelName) : 'in_app';
    }

    public function name(): string
    {
        return $this->channelName;
    }

    public function send(NotificationEnvelope $envelope): DeliveryResult
    {
        try {
            $payload = $envelope->payload();
            if (!is_array($payload)) {
                return DeliveryResult::failed($this->name(), 'In-app notification payload must be an array.');
            }

            $userId = self::stringField($payload, 'user_id');
            if ($userId === '') {
                return DeliveryResult::failed($this->name(), 'Payload field "user_id" is required.');
            }

            $title = self::nullableStringField($payload, 'title');
            $body = self::nullableStringField($payload, 'body');
            if ($title === null && $body === null) {
                return DeliveryResult::failed($this->name(), 'At least one of "title" or "body" must be provided.');
            }

            $data = self::arrayField($payload, 'data');
            $type = trim($envelope->type()) !== '' ? trim($envelope->type()) : 'in_app';
            $status = self::stringField($payload, 'status');
            $status = $status === 'read' ? 'read' : 'unread';

            $notification = InAppNotification::createNew(
                userId: $userId,
                type: $type,
                title: $title,
                body: $body,
                data: $data,
                status: $status,
            );

            $notificationId = $this->store->save($notification);

            $metadata = $envelope->metadata();
            $metadata['notification_id'] = $notificationId;
            $metadata['user_id'] = $userId;
            $metadata['notification_type'] = $type;

            return DeliveryResult::delivered($this->name(), $notificationId, $metadata);
        } catch (Throwable $exception) {
            return DeliveryResult::failed($this->name(), 'In-app notification error: ' . $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $payload */
    private static function stringField(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param array<string, mixed> $payload */
    private static function nullableStringField(array $payload, string $key): ?string
    {
        $value = self::stringField($payload, $key);
        return $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, scalar|null>
     */
    private static function arrayField(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $k => $item) {
            if (!is_string($k) || trim($k) === '') {
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $normalized[$k] = $item;
            }
        }

        return $normalized;
    }
}
