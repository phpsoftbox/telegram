<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

use function time;

final class ConversationState
{
    /** @var array<string, mixed> Собранные данные диалога. */
    private array $data;

    /** @var list<int> Идентификаторы сообщений для очистки. */
    private array $messageIds;

    private int $stepIndex;
    private ?int $startedAt;
    private ?int $updatedAt;
    private bool $cancelled = false;
    private bool $finished  = false;

    /**
     * @param array<string, mixed> $data Данные диалога.
     * @param list<int> $messageIds Сообщения, которые можно удалить.
     */
    public function __construct(
        private readonly string $name,
        private readonly string $chatId,
        array $data = [],
        array $messageIds = [],
        int $stepIndex = 0,
        ?int $startedAt = null,
        ?int $updatedAt = null,
        bool $cancelled = false,
        bool $finished = false,
    ) {
        $this->data       = $data;
        $this->messageIds = $messageIds;
        $this->stepIndex  = $stepIndex;
        $this->startedAt  = $startedAt ?? time();
        $this->updatedAt  = $updatedAt ?? time();
        $this->cancelled  = $cancelled;
        $this->finished   = $finished;
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            chatId: (string) ($payload['chat_id'] ?? ''),
            data: (array) ($payload['data'] ?? []),
            messageIds: (array) ($payload['message_ids'] ?? []),
            stepIndex: (int) ($payload['step_index'] ?? 0),
            startedAt: isset($payload['started_at']) ? (int) $payload['started_at'] : null,
            updatedAt: isset($payload['updated_at']) ? (int) $payload['updated_at'] : null,
            cancelled: (bool) ($payload['cancelled'] ?? false),
            finished: (bool) ($payload['finished'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'chat_id'     => $this->chatId,
            'data'        => $this->data,
            'message_ids' => $this->messageIds,
            'step_index'  => $this->stepIndex,
            'started_at'  => $this->startedAt,
            'updated_at'  => $this->updatedAt,
            'cancelled'   => $this->cancelled,
            'finished'    => $this->finished,
        ];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function chatId(): string
    {
        return $this->chatId;
    }

    public function stepIndex(): int
    {
        return $this->stepIndex;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function messageIds(): array
    {
        return $this->messageIds;
    }

    public function addMessageId(int $messageId): void
    {
        $this->messageIds[] = $messageId;
        $this->touch();
    }

    public function clearMessageIds(): void
    {
        $this->messageIds = [];
        $this->touch();
    }

    public function setData(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->touch();
    }

    public function incrementStep(): void
    {
        $this->stepIndex++;
        $this->touch();
    }

    public function markCancelled(): void
    {
        $this->cancelled = true;
        $this->touch();
    }

    public function markFinished(): void
    {
        $this->finished = true;
        $this->touch();
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }

    private function touch(): void
    {
        $this->updatedAt = time();
    }
}
