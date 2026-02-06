<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use RuntimeException;

final readonly class ArrayScenarioEntryPointDefinitionsProvider implements ScenarioEntryPointDefinitionsProviderInterface
{
    /**
     * @param list<ScenarioEntryPoint> $entryPoints
     */
    public function __construct(
        private array $entryPoints,
    ) {
    }

    /**
     * @return iterable<ScenarioEntryPoint>
     */
    public function entryPoints(): iterable
    {
        foreach ($this->entryPoints as $entryPoint) {
            if (!$entryPoint instanceof ScenarioEntryPoint) {
                throw new RuntimeException('Entry point definition must be ScenarioEntryPoint');
            }

            yield $entryPoint;
        }
    }
}
