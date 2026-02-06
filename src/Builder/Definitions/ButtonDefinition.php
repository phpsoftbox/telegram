<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Definitions;

final readonly class ButtonDefinition
{
    public function __construct(
        public string $name,
        public string $label,
        public string $action,
    ) {
    }
}
