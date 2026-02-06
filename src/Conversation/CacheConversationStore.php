<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use Psr\SimpleCache\CacheInterface;

use function is_array;

final readonly class CacheConversationStore implements ConversationStoreInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $prefix = 'telegram.conversation.',
    ) {
    }

    public function get(string $chatId): ?ConversationState
    {
        $payload = $this->cache->get($this->prefix . $chatId);
        if (!is_array($payload)) {
            return null;
        }

        return ConversationState::fromArray($payload);
    }

    public function save(ConversationState $state): void
    {
        $this->cache->set($this->prefix . $state->chatId(), $state->toArray());
    }

    public function delete(string $chatId): void
    {
        $this->cache->delete($this->prefix . $chatId);
    }
}
