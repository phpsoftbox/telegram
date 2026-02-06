<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

final class ArrayConversationStore implements ConversationStoreInterface
{
    /** @var array<string, ConversationState> Хранилище диалогов в памяти. */
    private array $storage = [];

    public function get(string $chatId): ?ConversationState
    {
        return $this->storage[$chatId] ?? null;
    }

    public function save(ConversationState $state): void
    {
        $this->storage[$state->chatId()] = $state;
    }

    public function delete(string $chatId): void
    {
        unset($this->storage[$chatId]);
    }
}
