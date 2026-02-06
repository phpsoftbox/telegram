<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

interface ScenarioEntryPointDefinitionsProviderInterface
{
    /**
     * @return iterable<ScenarioEntryPoint>
     */
    public function entryPoints(): iterable;
}
