<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use PhpSoftBox\Telegram\Bot\BotContext;

final class ConversationContext
{
    public function __construct(
        private readonly ConversationState $state,
        private readonly BotContext $botContext,
    ) {
    }

    public function state(): ConversationState
    {
        return $this->state;
    }

    public function bot(): BotContext
    {
        return $this->botContext;
    }

    public function data(): array
    {
        return $this->state->data();
    }

    public function set(string $key, mixed $value): void
    {
        $this->state->setData($key, $value);
    }

    public function chatId(): string
    {
        return $this->state->chatId();
    }

    public function sendMessage(string $text, array $options = []): void
    {
        $this->botContext->sendMessage($this->chatId(), $text, $options);
    }

    public function deleteMessage(int $messageId): void
    {
        $this->botContext->deleteMessage($this->chatId(), $messageId);
    }
}
