<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Scenario\Id\EntryPointIdInterface;
use RuntimeException;

use function is_callable;

final readonly class ScenarioFlowsBuilder
{
    public function __construct(
        private ScenarioBuilder $scenario,
    ) {
    }

    /**
     * @param callable(ScenarioEntryPointBuilder):void|null $configure
     */
    public function entryPoint(EntryPointIdInterface $name, ?callable $configure = null): ScenarioEntryPointBuilder
    {
        return $this->scenario->entryPoint($name, $configure);
    }

    public function import(string $path): self
    {
        $paths = $this->scenario->resolveImportPaths($path);

        foreach ($paths as $file) {
            $this->scenario->executeImportFile($file, function (string $resolvedFile): void {
                $register = require $resolvedFile;
                if (!is_callable($register)) {
                    throw new RuntimeException('Scenario flows import must return callable: ' . $resolvedFile);
                }

                $register($this);
            });
        }

        return $this;
    }

    public function done(): ScenarioBuilder
    {
        return $this->scenario;
    }
}
