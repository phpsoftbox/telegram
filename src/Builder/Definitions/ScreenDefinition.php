<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Definitions;

final readonly class ScreenDefinition
{
    /**
     * @param list<ScreenButton> $buttons
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $text,
        public ?string $image = null,
        public array $buttons = [],
        public ?string $textTemplate = null,
    ) {
    }
}
