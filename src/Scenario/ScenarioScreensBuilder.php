<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use RuntimeException;

use function is_callable;

final readonly class ScenarioScreensBuilder
{
    public function __construct(
        private ScenarioBuilder $scenario,
    ) {
    }

    /**
     * @param callable(ScenarioScreenBuilder):void|null $configure
     */
    public function screen(ScreenIdInterface $name, ?callable $configure = null): ScenarioScreenBuilder
    {
        return $this->scenario->screen($name, $configure);
    }

    public function import(string $path): self
    {
        $paths = $this->scenario->resolveImportPaths($path);

        foreach ($paths as $file) {
            $this->scenario->executeImportFile($file, function (string $resolvedFile): void {
                $register = require $resolvedFile;
                if (!is_callable($register)) {
                    throw new RuntimeException('Scenario screens import must return callable: ' . $resolvedFile);
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
