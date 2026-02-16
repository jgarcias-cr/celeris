<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp;

use JsonException;

/**
 * Immutable in-app notification record used by stores/channels.
 */
final class InAppNotification
{
    /** @param array<string, scalar|null> $data */
    public function __construct(
        private string $id,
        private string $userId,
        private string $type,
        private ?string $title,
        private ?string $body,
        private array $data,
        private string $status = 'unread',
        private float $createdAtUnix = 0.0,
        private ?float $readAtUnix = null,
    ) {
        $this->id = trim($this->id);
        $this->userId = trim($this->userId);
        $this->type = trim($this->type) !== '' ? trim($this->type) : 'in_app';
        $this->title = self::nullable($this->title);
        $this->body = self::nullable($this->body);
        $this->data = self::normalizeData($this->data);
        $this->status = self::normalizeStatus($this->status);
        $this->createdAtUnix = $this->createdAtUnix > 0 ? $this->createdAtUnix : microtime(true);
        $this->readAtUnix = $this->readAtUnix !== null && $this->readAtUnix > 0 ? $this->readAtUnix : null;
    }

    /** @param array<string, scalar|null> $data */
    public static function createNew(
        string $userId,
        string $type,
        ?string $title,
        ?string $body,
        array $data = [],
        string $status = 'unread',
    ): self {
        return new self(
            id: self::generateId(),
            userId: $userId,
            type: $type,
            title: $title,
            body: $body,
            data: $data,
            status: $status,
            createdAtUnix: microtime(true),
            readAtUnix: null,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            userId: (string) ($row['user_id'] ?? ''),
            type: (string) ($row['notification_type'] ?? 'in_app'),
            title: self::nullable((string) ($row['title'] ?? '')),
            body: self::nullable((string) ($row['body'] ?? '')),
            data: self::decodeData((string) ($row['data_json'] ?? '{}')),
            status: (string) ($row['status'] ?? 'unread'),
            createdAtUnix: (float) ($row['created_at_unix'] ?? 0),
            readAtUnix: isset($row['read_at_unix']) ? (float) $row['read_at_unix'] : null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function body(): ?string
    {
        return $this->body;
    }

    /** @return array<string, scalar|null> */
    public function data(): array
    {
        return $this->data;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAtUnix(): float
    {
        return $this->createdAtUnix;
    }

    public function readAtUnix(): ?float
    {
        return $this->readAtUnix;
    }

    public function withReadAt(?float $readAtUnix): self
    {
        $copy = clone $this;
        $copy->readAtUnix = $readAtUnix !== null && $readAtUnix > 0 ? $readAtUnix : null;
        $copy->status = $copy->readAtUnix !== null ? 'read' : 'unread';
        return $copy;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'notification_type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'status' => $this->status,
            'created_at_unix' => $this->createdAtUnix,
            'read_at_unix' => $this->readAtUnix,
        ];
    }

    /** @return array<string, mixed> */
    public function toInsertParams(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'notification_type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data_json' => self::encodeData($this->data),
            'status' => $this->status,
            'created_at_unix' => $this->createdAtUnix,
            'read_at_unix' => $this->readAtUnix,
        ];
    }

    private static function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean !== '' ? $clean : null;
    }

    private static function normalizeStatus(string $status): string
    {
        $clean = strtolower(trim($status));
        return $clean === 'read' ? 'read' : 'unread';
    }

    /** @param array<string, scalar|null> $data */
    private static function normalizeData(array $data): array
    {
        $normalized = [];
        foreach ($data as $key => $value) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$k] = $value;
            }
        }

        return $normalized;
    }

    /** @param array<string, scalar|null> $data */
    private static function encodeData(array $data): string
    {
        try {
            return (string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{}';
        }
    }

    /** @return array<string, scalar|null> */
    private static function decodeData(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
