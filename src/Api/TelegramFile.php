<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Api;

final class TelegramFile
{
    public function __construct(
        private readonly string $fileId,
        private readonly ?string $fileUniqueId = null,
        private readonly ?int $fileSize = null,
        private readonly ?string $filePath = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            fileId: (string) ($payload['file_id'] ?? ''),
            fileUniqueId: isset($payload['file_unique_id']) ? (string) $payload['file_unique_id'] : null,
            fileSize: isset($payload['file_size']) ? (int) $payload['file_size'] : null,
            filePath: isset($payload['file_path']) ? (string) $payload['file_path'] : null,
        );
    }

    public function fileId(): string
    {
        return $this->fileId;
    }

    public function fileUniqueId(): ?string
    {
        return $this->fileUniqueId;
    }

    public function fileSize(): ?int
    {
        return $this->fileSize;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }
}
