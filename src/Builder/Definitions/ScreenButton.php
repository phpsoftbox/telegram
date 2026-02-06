<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Definitions;

final readonly class ScreenButton
{
    public function __construct(
        public ButtonDefinition $button,
        public int $row = 1,
        public ?int $position = null,
    ) {
    }
}
