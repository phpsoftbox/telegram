<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowTarget;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;

use function trim;

final class ScenarioRouteBuilder
{
    /**
     * @var list<array{
     *   target: FlowTarget,
     *   guard_class: ?string,
     *   guard_args: array<string,mixed>,
     *   expected_screen: ?string
     * }>
     */
    private array $routes = [];

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

        $this->routes[] = [
            'target'          => FlowTarget::action($actionValue),
            'guard_class'     => $guardClass,
            'guard_args'      => $guardArgs,
            'expected_screen' => $expectedScreenValue,
        ];

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

        $this->routes[] = [
            'target'          => FlowTarget::screen($screenValue),
            'guard_class'     => $guardClass,
            'guard_args'      => $guardArgs,
            'expected_screen' => $screenValue,
        ];

        return $this;
    }

    /**
     * @return list<array{
     *   target: FlowTarget,
     *   guard_class: ?string,
     *   guard_args: array<string,mixed>,
     *   expected_screen: ?string
     * }>
     */
    public function routes(): array
    {
        return $this->routes;
    }
}
