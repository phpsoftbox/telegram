<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Conversation;

final class StepResult
{
    private function __construct(
        private readonly bool $accepted,
        private readonly mixed $value = null,
        private readonly ?string $message = null,
    ) {
    }

    public static function accept(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function reject(?string $message = null): self
    {
        return new self(false, null, $message);
    }

    public function accepted(): bool
    {
        return $this->accepted;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
