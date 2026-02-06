<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

interface ConversationStoreInterface
{
    public function get(string $chatId): ?ConversationState;

    public function save(ConversationState $state): void;

    public function delete(string $chatId): void;
}
