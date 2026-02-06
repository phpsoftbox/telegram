<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Update;

use InvalidArgumentException;
use JsonException;

use function is_array;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final class Update
{
    private ?Message $message = null;

    public function __construct(
        private readonly array $payload,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    public static function fromJson(string $json): self
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid JSON in update.', previous: $exception);
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid update payload.');
        }

        return new self($decoded);
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function updateId(): ?int
    {
        return isset($this->payload['update_id']) ? (int) $this->payload['update_id'] : null;
    }

    public function message(): ?Message
    {
        if ($this->message !== null) {
            return $this->message;
        }

        $message = $this->payload['message'] ?? null;
        if (!is_array($message)) {
            return null;
        }

        $this->message = new Message($message);

        return $this->message;
    }

    public function chatId(): int|string|null
    {
        return $this->message()?->chatId();
    }

    public function fromId(): ?int
    {
        return $this->message()?->fromId();
    }

    public function text(): ?string
    {
        return $this->message()?->text();
    }

    public function type(): MessageTypeEnum
    {
        return $this->message()?->type() ?? MessageTypeEnum::UNKNOWN;
    }
}
