<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Runtime;

final readonly class ButtonGroupMenuComposer
{
    public function __construct(
        private ButtonGroupProvider $groups,
        private ActionRegistry $actions,
    ) {
    }

    public function forGroup(string $groupName): ButtonGroupMenuBuilder
    {
        return new ButtonGroupMenuBuilder(
            actions: $this->actions,
            baseButtons: $this->groups->forGroup($groupName),
        );
    }
}
