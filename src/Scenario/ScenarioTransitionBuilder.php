<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowTarget;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;

use function trim;

final class ScenarioTransitionBuilder
{
    public function __construct(
        private readonly ScenarioBuilder $scenario,
        private readonly string $transitionName,
    ) {
    }

    public function description(string $description): self
    {
        $this->scenario->setTransitionDescription($this->transitionName, $description);

        return $this;
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public function toAction(
        ActionIdInterface $actionId,
        ?string $guardClass = null,
        array $guardArgs = [],
        ?ScreenIdInterface $expectedScreen = null,
    ): self {
        $actionValue         = trim($actionId->value);
        $expectedScreenValue = $expectedScreen !== null ? trim($expectedScreen->value) : null;

        $this->scenario->addTransitionRoute($this->transitionName, [
            'target'          => FlowTarget::action($actionValue),
            'guard_class'     => $guardClass,
            'guard_args'      => $guardArgs,
            'expected_screen' => $expectedScreenValue,
        ]);

        return $this;
    }

    /**
     * @param array<string,mixed> $guardArgs
     */
    public function toScreen(
        ScreenIdInterface $screenId,
        ?string $guardClass = null,
        array $guardArgs = [],
    ): self {
        $screenValue = trim($screenId->value);

        $this->scenario->addTransitionRoute($this->transitionName, [
            'target'          => FlowTarget::screen($screenValue),
            'guard_class'     => $guardClass,
            'guard_args'      => $guardArgs,
            'expected_screen' => $screenValue,
        ]);

        return $this;
    }

    public function done(): ScenarioBuilder
    {
        return $this->scenario;
    }
}
