<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Flow\FlowTarget;
use PhpSoftBox\Telegram\Scenario\Id\ActionIdInterface;
use PhpSoftBox\Telegram\Scenario\Id\ScreenIdInterface;
use RuntimeException;

use function trim;

final class ScenarioEntryPointBuilder
{
    private ?FlowTarget $target = null;
    private ?string $guardClass = null;
    private string $description = '';

    /**
     * @var array<string,mixed>
     */
    private array $guardArgs = [];
    private int $priority    = 100;
    private bool $registered = false;

    public function __construct(
        private readonly ScenarioBuilder $scenario,
        private readonly string $name,
        ?string $description = null,
    ) {
        $this->description = trim((string) ($description ?? ''));
    }

    public function toAction(ActionIdInterface $actionId): self
    {
        $this->target = FlowTarget::action(trim($actionId->value));

        return $this;
    }

    public function toScreen(ScreenIdInterface $screenId): self
    {
        $this->target = FlowTarget::screen(trim($screenId->value));

        return $this;
    }

    /**
     * @param array<string,mixed> $args
     */
    public function guard(string $guardClass, array $args = []): self
    {
        $this->guardClass = $guardClass;
        $this->guardArgs  = $args;

        return $this;
    }

    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        if (!$this->target instanceof FlowTarget) {
            throw new RuntimeException('Entry point "' . $this->name . '" target is not configured.');
        }

        $this->scenario->addEntryPoint(new ScenarioEntryPoint(
            name: $this->name,
            target: $this->target,
            guardClass: $this->guardClass,
            guardArgs: $this->guardArgs,
            priority: $this->priority,
            description: $this->description,
        ));

        $this->registered = true;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }
}
