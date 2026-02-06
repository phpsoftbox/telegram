<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowTarget;

final readonly class ScenarioEntryPointDecision
{
    public function __construct(
        public string $name,
        public FlowTarget $target,
        public ?string $guardId = null,
    ) {
    }
}
