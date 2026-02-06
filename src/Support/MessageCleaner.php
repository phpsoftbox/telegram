<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Support;

use PhpSoftBox\Telegram\Api\TelegramApiException;
use PhpSoftBox\Telegram\Api\TelegramClient;

final class MessageCleaner
{
    public function __construct(
        private readonly TelegramClient $client,
        private readonly bool $ignoreErrors = true,
    ) {
    }

    /**
     * @param list<int> $messageIds Список сообщений для удаления.
     */
    public function clean(int|string $chatId, array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            try {
                $this->client->deleteMessage($chatId, $messageId);
            } catch (TelegramApiException $exception) {
                if (!$this->ignoreErrors) {
                    throw $exception;
                }
            }
        }
    }
}
