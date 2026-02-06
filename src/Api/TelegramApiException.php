<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Api;

use RuntimeException;

final class TelegramApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $errorCode = null,
        private readonly array $payload = [],
    ) {
        parent::__construct($message, $errorCode ?? 0);
    }

    public function errorCode(): ?int
    {
        return $this->errorCode;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
