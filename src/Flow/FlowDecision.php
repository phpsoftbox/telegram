<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

final readonly class FlowDecision
{
    public function __construct(
        public string $from,
        public string $event,
        public FlowTarget $target,
        public ?string $guardId = null,
        public ?string $expectedScreen = null,
    ) {
    }
}
