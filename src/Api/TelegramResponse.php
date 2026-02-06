<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Api;

final class TelegramResponse
{
    public function __construct(
        private readonly bool $ok,
        private readonly mixed $result,
        private readonly ?string $description = null,
        private readonly ?int $errorCode = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            ok: (bool) ($payload['ok'] ?? false),
            result: $payload['result'] ?? null,
            description: isset($payload['description']) ? (string) $payload['description'] : null,
            errorCode: isset($payload['error_code']) ? (int) $payload['error_code'] : null,
        );
    }

    public function ok(): bool
    {
        return $this->ok;
    }

    public function result(): mixed
    {
        return $this->result;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function errorCode(): ?int
    {
        return $this->errorCode;
    }
}
