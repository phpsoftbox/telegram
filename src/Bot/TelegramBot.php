<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Api\TelegramClient;

final readonly class TelegramBot
{
    public function __construct(
        private string $name,
        private string $token,
        private TelegramClient $client,
        private UpdateHandlerInterface $handler,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function token(): string
    {
        return $this->token;
    }

    public function client(): TelegramClient
    {
        return $this->client;
    }

    public function handler(): UpdateHandlerInterface
    {
        return $this->handler;
    }
}
