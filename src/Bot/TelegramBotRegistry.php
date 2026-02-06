<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Api\TelegramClient;

use function array_keys;

final class TelegramBotRegistry
{
    /**
     * @var array<string, TelegramBot>
     */
    private array $bots = [];

    public function __construct(
        private readonly string $defaultBot,
        array $bots = [],
    ) {
        foreach ($bots as $bot) {
            if ($bot instanceof TelegramBot) {
                $this->bots[$bot->name()] = $bot;
            }
        }
    }

    public function has(string $name): bool
    {
        return isset($this->bots[$name]);
    }

    public function defaultName(): string
    {
        return $this->defaultBot;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->bots);
    }

    public function bot(?string $name = null): ?TelegramBot
    {
        $name = $name !== null && $name !== '' ? $name : $this->defaultBot;

        return $this->bots[$name] ?? null;
    }

    public function handler(?string $name = null): UpdateHandlerInterface
    {
        return $this->bot($name)?->handler() ?? new NullUpdateHandler();
    }

    public function client(?string $name = null): ?TelegramClient
    {
        return $this->bot($name)?->client();
    }

    public function token(?string $name = null): ?string
    {
        return $this->bot($name)?->token();
    }
}
