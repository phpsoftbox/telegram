<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Builder\Provider;

use PhpSoftBox\Telegram\Builder\Definitions\ActionDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use RuntimeException;

final readonly class TelegramDefinitionsProvider
{
    public function __construct(
        private TelegramBotBuilder $builder,
    ) {
    }

    public function getScreen(string $name): ScreenDefinition
    {
        $definition = $this->builder->screen($name);
        if (!$definition instanceof ScreenDefinition) {
            throw new RuntimeException('Screen definition not found: ' . $name);
        }

        return $definition;
    }

    /**
     * @return array<string, ScreenDefinition>
     */
    public function getScreens(): array
    {
        return $this->builder->screens();
    }

    public function getButton(string $name): ButtonDefinition
    {
        $definition = $this->builder->button($name);
        if (!$definition instanceof ButtonDefinition) {
            throw new RuntimeException('Button definition not found: ' . $name);
        }

        return $definition;
    }

    /**
     * @return array<string, ButtonDefinition>
     */
    public function getButtons(): array
    {
        return $this->builder->buttons();
    }

    public function getAction(string $name): ActionDefinition
    {
        $definition = $this->builder->action($name);
        if (!$definition instanceof ActionDefinition) {
            throw new RuntimeException('Action definition not found: ' . $name);
        }

        return $definition;
    }

    /**
     * @return array<string, ActionDefinition>
     */
    public function getActions(): array
    {
        return $this->builder->actions();
    }
}
