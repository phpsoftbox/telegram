<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Update;

use function array_key_last;
use function is_array;

final class Message
{
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function messageId(): ?int
    {
        return isset($this->payload['message_id']) ? (int) $this->payload['message_id'] : null;
    }

    public function chatId(): int|string|null
    {
        return $this->payload['chat']['id'] ?? null;
    }

    public function fromId(): ?int
    {
        return isset($this->payload['from']['id']) ? (int) $this->payload['from']['id'] : null;
    }

    public function text(): ?string
    {
        return isset($this->payload['text']) ? (string) $this->payload['text'] : null;
    }

    public function contact(): ?array
    {
        $contact = $this->payload['contact'] ?? null;
        if (!is_array($contact)) {
            return null;
        }

        return $contact;
    }

    public function contactPhone(): ?string
    {
        $contact = $this->contact();
        if ($contact === null) {
            return null;
        }

        return isset($contact['phone_number']) ? (string) $contact['phone_number'] : null;
    }

    public function contactUserId(): ?int
    {
        $contact = $this->contact();
        if ($contact === null) {
            return null;
        }

        return isset($contact['user_id']) ? (int) $contact['user_id'] : null;
    }

    public function type(): MessageTypeEnum
    {
        if (isset($this->payload['contact'])) {
            return MessageTypeEnum::CONTACT;
        }
        if (isset($this->payload['text'])) {
            return MessageTypeEnum::TEXT;
        }
        if (isset($this->payload['photo'])) {
            return MessageTypeEnum::PHOTO;
        }
        if (isset($this->payload['video'])) {
            return MessageTypeEnum::VIDEO;
        }
        if (isset($this->payload['audio'])) {
            return MessageTypeEnum::AUDIO;
        }
        if (isset($this->payload['voice'])) {
            return MessageTypeEnum::VOICE;
        }
        if (isset($this->payload['document'])) {
            return MessageTypeEnum::DOCUMENT;
        }

        return MessageTypeEnum::UNKNOWN;
    }

    public function value(): mixed
    {
        return match ($this->type()) {
            MessageTypeEnum::CONTACT  => $this->contactPhone(),
            MessageTypeEnum::TEXT     => $this->text(),
            MessageTypeEnum::PHOTO    => $this->photoFileId(),
            MessageTypeEnum::VIDEO    => $this->fileIdFrom('video'),
            MessageTypeEnum::AUDIO    => $this->fileIdFrom('audio'),
            MessageTypeEnum::VOICE    => $this->fileIdFrom('voice'),
            MessageTypeEnum::DOCUMENT => $this->fileIdFrom('document'),
            default                   => null,
        };
    }

    public function photoFileId(): ?string
    {
        $photo = $this->payload['photo'] ?? null;
        if (!is_array($photo) || $photo === []) {
            return null;
        }

        $last = $photo[array_key_last($photo)] ?? null;
        if (!is_array($last)) {
            return null;
        }

        return isset($last['file_id']) ? (string) $last['file_id'] : null;
    }

    private function fileIdFrom(string $key): ?string
    {
        $block = $this->payload[$key] ?? null;
        if (!is_array($block)) {
            return null;
        }

        return isset($block['file_id']) ? (string) $block['file_id'] : null;
    }
}
