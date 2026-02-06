<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Update;

use function is_array;

final class CallbackQuery
{
    private ?Message $message = null;

    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function id(): ?string
    {
        return isset($this->payload['id']) ? (string) $this->payload['id'] : null;
    }

    public function data(): ?string
    {
        return isset($this->payload['data']) ? (string) $this->payload['data'] : null;
    }

    public function chatId(): int|string|null
    {
        return $this->message()?->chatId();
    }

    public function fromId(): ?int
    {
        return isset($this->payload['from']['id']) ? (int) $this->payload['from']['id'] : null;
    }

    public function messageId(): ?int
    {
        return $this->message()?->messageId();
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
}
