<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Bot;

use PhpSoftBox\Telegram\Api\TelegramClient;
use PhpSoftBox\Telegram\Conversation\ConversationManager;
use PhpSoftBox\Telegram\Support\MessageCleaner;

final class BotContext
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly ?ConversationManager $conversations = null,
        private readonly ?MessageCleaner $messageCleaner = null,
    ) {
    }

    public function client(): TelegramClient
    {
        return $this->client;
    }

    public function conversations(): ?ConversationManager
    {
        return $this->conversations;
    }

    public function messageCleaner(): ?MessageCleaner
    {
        return $this->messageCleaner;
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): void
    {
        $this->client->sendMessage($chatId, $text, $options);
    }

    public function deleteMessage(int|string $chatId, int $messageId): void
    {
        $this->client->deleteMessage($chatId, $messageId);
    }
}
