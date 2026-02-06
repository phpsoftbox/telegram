<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

final readonly class ScreenButton
{
    public function __construct(
        public string $label,
        public string $action,
        public int $row = 1,
        public int $position = 1,
    ) {
    }
}
