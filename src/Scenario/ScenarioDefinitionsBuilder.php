<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonGroupPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ButtonPresetIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\CallbackEventIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\TransitionIdInterface;
use RuntimeException;

use function is_callable;

final readonly class ScenarioDefinitionsBuilder
{
    public function __construct(
        private ScenarioBuilder $scenario,
    ) {
    }

    public function action(
        ActionIdInterface $action,
        string $handlerClass,
        ?string $method = null,
        ?string $description = null,
    ): self {
        $this->scenario->bindAction($action, $handlerClass, $method, $description);

        return $this;
    }

    public function button(
        ButtonPresetIdInterface $preset,
        string $label,
        CallbackEventIdInterface $event,
        ?string $callbackData = null,
        ?string $description = null,
    ): self {
        $this->scenario->button($preset, $label, $event, $callbackData, $description);

        return $this;
    }

    /**
     * @param callable(ScenarioButtonGroupBuilder):void|null $configure
     */
    public function buttonGroup(
        ButtonGroupPresetIdInterface $group,
        ?callable $configure = null,
    ): self {
        $this->scenario->buttonGroup($group, $configure);

        return $this;
    }

    /**
     * @param callable(ScenarioButtonGroupBuilder):void|null $configure
     */
    public function buttonGroupPreset(
        ButtonGroupPresetIdInterface $group,
        ?callable $configure = null,
    ): self {
        return $this->buttonGroup($group, $configure);
    }

    /**
     * @param callable(ScenarioTransitionBuilder):void|null $configure
     */
    public function transition(
        TransitionIdInterface $name,
        ?callable $configure = null,
        ?string $description = null,
    ): self {
        $this->scenario->transition($name, $configure, $description);

        return $this;
    }

    public function import(string $path): self
    {
        $paths = $this->scenario->resolveImportPaths($path);

        foreach ($paths as $file) {
            $this->scenario->executeImportFile($file, function (string $resolvedFile): void {
                $register = require $resolvedFile;
                if (!is_callable($register)) {
                    throw new RuntimeException('Scenario definitions import must return callable: ' . $resolvedFile);
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
