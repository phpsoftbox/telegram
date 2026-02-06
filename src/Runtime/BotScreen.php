<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

use function trim;

final class BotScreen
{
    /**
     * @param list<string> $buttons
     */
    public function __construct(
        private string $name,
        private string $title,
        private string $text,
        private ?string $image = null,
        private array $buttons = [],
    ) {
        $this->image = $this->normalizeNullableString($this->image);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $this->normalizeNullableString($image);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getButtons(): array
    {
        return $this->buttons;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
