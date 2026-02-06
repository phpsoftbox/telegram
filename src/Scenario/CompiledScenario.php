<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario;

use PhpSoftBox\Telegram\Builder\Definitions\ActionDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ActionTypeEnum;
use PhpSoftBox\Telegram\Builder\Definitions\ButtonDefinition;
use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Builder\TelegramBotBuilder;
use PhpSoftBox\Telegram\Flow\FlowTransition;

final readonly class CompiledScenario
{
    /**
     * @param array<string, ScreenDefinition> $screens
     * @param array<string, ButtonDefinition> $buttons
     * @param array<string, ActionDefinition> $actions
     * @param list<FlowTransition> $transitions
     * @param array<string, ActionHandlerDefinition> $actionHandlers
     * @param list<ScenarioEntryPoint> $entryPoints
     * @param array<string, list<array{
     *   name:string,
     *   label:string,
     *   action:string,
     *   row:int,
     *   position:int
     * }>> $buttonGroups
     */
    public function __construct(
        public array $screens,
        public array $buttons,
        public array $actions,
        public array $transitions,
        public array $actionHandlers = [],
        public array $entryPoints = [],
        public array $buttonGroups = [],
    ) {
    }

    public function registerDefinitions(TelegramBotBuilder $builder): void
    {
        foreach ($this->actions as $definition) {
            $builder->defineAction($definition);
        }

        foreach ($this->buttons as $definition) {
            $builder->defineButton($definition);
        }

        foreach ($this->screens as $definition) {
            $builder->defineScreen($definition);
        }
    }

    public function registerActionHandlers(TelegramBotBuilder $builder): void
    {
        foreach ($this->actionHandlers as $action => $handler) {
            $actionDefinition = $this->actions[$action] ?? null;
            if (!$actionDefinition instanceof ActionDefinition) {
                continue;
            }

            if ($actionDefinition->type !== ActionTypeEnum::CALLBACK) {
                continue;
            }

            $method = $handler->method === '__invoke' ? null : $handler->method;
            $builder->onAction($action, $handler->handlerClass, $method);
        }
    }

    /**
     * @return array<string, array{class:string,method:string}>
     */
    public function actionHandlersMap(): array
    {
        $result = [];
        foreach ($this->actionHandlers as $action => $handler) {
            $result[$action] = [
                'class'  => $handler->handlerClass,
                'method' => $handler->method,
            ];
        }

        return $result;
    }

    public function entryPointsProvider(): ScenarioEntryPointDefinitionsProviderInterface
    {
        return new ArrayScenarioEntryPointDefinitionsProvider($this->entryPoints);
    }

    /**
     * @return array<string, list<array{name:string,label:string,action:string,row:int,position:int}>>
     */
    public function buttonGroupsProviderDefinitions(): array
    {
        return $this->buttonGroups;
    }
}
