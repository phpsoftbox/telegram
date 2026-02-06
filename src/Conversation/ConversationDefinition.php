<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

final class ConversationDefinition
{
    /** @var list<ConversationStepInterface> Список шагов. */
    private array $steps;

    /** @var list<string> Ключевые слова для отмены. */
    private array $cancelKeywords = ['/cancel'];

    private ?string $cancelMessage = 'Operation cancelled.';
    private ?string $finishMessage = null;
    private bool $cleanupMessages  = false;
    private $finishHandler         = null;
    private $cancelHandler         = null;

    /**
     * @param list<ConversationStepInterface> $steps Шаги диалога.
     */
    public function __construct(
        private readonly string $name,
        array $steps,
    ) {
        $this->steps = $steps;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<ConversationStepInterface>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    public function step(int $index): ?ConversationStepInterface
    {
        return $this->steps[$index] ?? null;
    }

    public function cancelKeywords(): array
    {
        return $this->cancelKeywords;
    }

    public function cancelMessage(): ?string
    {
        return $this->cancelMessage;
    }

    public function finishMessage(): ?string
    {
        return $this->finishMessage;
    }

    public function finishHandler(): ?callable
    {
        return $this->finishHandler;
    }

    public function cancelHandler(): ?callable
    {
        return $this->cancelHandler;
    }

    public function cleanupMessages(): bool
    {
        return $this->cleanupMessages;
    }

    public function withCancelKeywords(array $keywords): self
    {
        $clone                 = clone $this;
        $clone->cancelKeywords = $keywords;

        return $clone;
    }

    public function withCancelMessage(?string $message): self
    {
        $clone                = clone $this;
        $clone->cancelMessage = $message;

        return $clone;
    }

    public function withFinishMessage(?string $message): self
    {
        $clone                = clone $this;
        $clone->finishMessage = $message;

        return $clone;
    }

    public function onFinish(callable $handler): self
    {
        $clone                = clone $this;
        $clone->finishHandler = $handler;

        return $clone;
    }

    public function onCancel(callable $handler): self
    {
        $clone                = clone $this;
        $clone->cancelHandler = $handler;

        return $clone;
    }

    public function withCleanupMessages(bool $cleanup = true): self
    {
        $clone                  = clone $this;
        $clone->cleanupMessages = $cleanup;

        return $clone;
    }
}
