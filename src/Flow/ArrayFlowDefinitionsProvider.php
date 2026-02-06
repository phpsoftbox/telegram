<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Flow;

use RuntimeException;

use function array_values;

final readonly class ArrayFlowDefinitionsProvider implements FlowDefinitionsProviderInterface
{
    /**
     * @param list<FlowTransition> $transitions
     */
    public function __construct(
        private array $transitions = [],
    ) {
    }

    public function transitions(): array
    {
        foreach ($this->transitions as $index => $transition) {
            if (!$transition instanceof FlowTransition) {
                throw new RuntimeException('Transition definition must be FlowTransition at index ' . $index . '.');
            }
        }

        return array_values($this->transitions);
    }
}
