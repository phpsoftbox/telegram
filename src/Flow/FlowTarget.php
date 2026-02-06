<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use RuntimeException;

use function trim;

final readonly class FlowTarget
{
    public function __construct(
        public FlowTargetsEnum $type,
        public string $id,
    ) {
        $id = trim($this->id);
        if ($id !== $this->id) {
            throw new RuntimeException('Flow target values must be trimmed.');
        }

        if ($id === '') {
            throw new RuntimeException('Flow target id must not be empty.');
        }
    }

    public static function screen(string $id): self
    {
        return new self(FlowTargetsEnum::SCREEN, $id);
    }

    public static function action(string $id): self
    {
        return new self(FlowTargetsEnum::ACTION, $id);
    }

    public function isScreen(): bool
    {
        return $this->type === FlowTargetsEnum::SCREEN;
    }

    public function isAction(): bool
    {
        return $this->type === FlowTargetsEnum::ACTION;
    }
}
