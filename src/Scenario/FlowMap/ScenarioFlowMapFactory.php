<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use PhpSoftBox\Telegram\Builder\Definitions\ScreenDefinition;
use PhpSoftBox\Telegram\Flow\FlowTransition;
use PhpSoftBox\Telegram\Scenario\CompiledScenario;

use function array_key_exists;
use function array_values;
use function basename;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;
use function trim;

final class ScenarioFlowMapFactory
{
    public function build(CompiledScenario $scenario, bool $includeButtons = true): ScenarioFlowMap
    {
        $nodeById                  = [];
        $edges                     = [];
        $edgeKeys                  = [];
        $buttonSourceByScreenEvent = [];
        $buttonTransitionEvents    = [];

        foreach ($scenario->screens as $screen) {
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $this->screenNodeId($screen->name),
                type: 'screen',
                label: $this->screenLabel($screen),
            ));
        }

        foreach ($scenario->actions as $action) {
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $this->actionNodeId($action->name),
                type: 'action',
                label: $action->name,
            ));
        }

        if ($includeButtons) {
            foreach ($scenario->buttons as $button) {
                $this->addNode($nodeById, new ScenarioFlowMapNode(
                    id: $this->buttonNodeId($button->name),
                    type: 'button',
                    label: $button->label,
                ));
                $this->addNode($nodeById, new ScenarioFlowMapNode(
                    id: $this->actionNodeId($button->action),
                    type: 'action',
                    label: $button->action,
                ));
            }

            foreach ($scenario->screens as $screen) {
                $from = $this->screenNodeId($screen->name);
                foreach ($screen->buttons as $screenButton) {
                    $buttonName = trim($screenButton->button->name);
                    if ($buttonName === '') {
                        continue;
                    }
                    $buttonNodeId = $this->buttonNodeId($buttonName);

                    $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                        from: $from,
                        to: $buttonNodeId,
                        kind: 'screen_button',
                    ));

                    $buttonSourceByScreenEvent[$screen->name][$screenButton->button->action][] = $buttonNodeId;
                }
            }

        }

        foreach ($scenario->entryPoints as $index => $entryPoint) {
            $entryNodeId = $this->entryNodeId($entryPoint->name, $index);
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $entryNodeId,
                type: 'entry',
                label: $entryPoint->name,
            ));

            $targetNodeId = $entryPoint->target->isScreen()
                ? $this->screenNodeId($entryPoint->target->id)
                : $this->actionNodeId($entryPoint->target->id);
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $targetNodeId,
                type: $entryPoint->target->isScreen() ? 'screen' : 'action',
                label: $entryPoint->target->id,
            ));

            $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                from: $entryNodeId,
                to: $targetNodeId,
                kind: 'entry',
                label: $entryPoint->guardClass !== null ? $this->shortClassName($entryPoint->guardClass) : '',
                event: 'entry:' . $entryPoint->name,
                targetType: $entryPoint->target->type->value,
                targetId: $entryPoint->target->id,
                guardClass: $entryPoint->guardClass,
                guardArgs: $entryPoint->guardArgs,
                expectedScreen: $entryPoint->target->isScreen() ? $entryPoint->target->id : null,
            ));
        }

        foreach ($scenario->transitions as $transition) {
            $this->appendTransition(
                nodeById: $nodeById,
                edges: $edges,
                edgeKeys: $edgeKeys,
                transition: $transition,
                buttonSourceByScreenEvent: $buttonSourceByScreenEvent,
                buttonTransitionEvents: $buttonTransitionEvents,
            );
        }

        if ($includeButtons) {
            foreach ($scenario->buttons as $button) {
                $buttonNodeId = $this->buttonNodeId($button->name);
                $event        = trim($button->action);
                if ($event !== '' && ($buttonTransitionEvents[$buttonNodeId][$event] ?? false)) {
                    continue;
                }

                $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                    from: $buttonNodeId,
                    to: $this->actionNodeId($button->action),
                    kind: 'button_action',
                    event: $button->action,
                    targetType: 'action',
                    targetId: $button->action,
                ));
            }
        }

        foreach ($scenario->actionHandlersMap() as $action => $handler) {
            $handlerClass = trim((string) ($handler['class'] ?? ''));
            if ($handlerClass === '') {
                continue;
            }

            $handlerMethod = trim((string) ($handler['method'] ?? '__invoke'));
            if ($handlerMethod === '') {
                $handlerMethod = '__invoke';
            }

            $actionNodeId  = $this->actionNodeId($action);
            $handlerNodeId = $this->handlerNodeId($action, $handlerClass, $handlerMethod);

            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $actionNodeId,
                type: 'action',
                label: $action,
            ));
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $handlerNodeId,
                type: 'handler',
                label: $this->shortClassName($handlerClass) . '::' . $handlerMethod,
            ));

            $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                from: $actionNodeId,
                to: $handlerNodeId,
                kind: 'action_handler',
            ));
        }

        return new ScenarioFlowMap(
            nodes: $this->nodesSorted($nodeById),
            edges: $edges,
        );
    }

    private function appendTransition(
        array &$nodeById,
        array &$edges,
        array &$edgeKeys,
        FlowTransition $transition,
        array $buttonSourceByScreenEvent = [],
        array &$buttonTransitionEvents = [],
    ): void {
        $toNodeId = $transition->target->isScreen()
            ? $this->screenNodeId($transition->target->id)
            : $this->actionNodeId($transition->target->id);

        $fromNodeIds   = [];
        $buttonSources = $buttonSourceByScreenEvent[$transition->from][$transition->event] ?? [];
        if (is_array($buttonSources) && $buttonSources !== []) {
            foreach ($buttonSources as $buttonNodeId) {
                if (!is_string($buttonNodeId)) {
                    continue;
                }
                $candidate = trim($buttonNodeId);
                if ($candidate === '') {
                    continue;
                }

                $fromNodeIds[] = $candidate;
            }
        }

        if ($fromNodeIds === []) {
            $fromNodeId    = $this->screenNodeId($transition->from);
            $fromNodeIds[] = $fromNodeId;
            $this->addNode($nodeById, new ScenarioFlowMapNode(
                id: $fromNodeId,
                type: 'screen',
                label: $transition->from,
            ));
        }

        $this->addNode($nodeById, new ScenarioFlowMapNode(
            id: $toNodeId,
            type: $transition->target->isScreen() ? 'screen' : 'action',
            label: $transition->target->id,
        ));

        $label = $transition->guardClass !== null ? $this->shortClassName($transition->guardClass) : '';
        foreach ($fromNodeIds as $fromNodeId) {
            if (
                str_starts_with($fromNodeId, 'button:')
                && $transition->event !== ''
            ) {
                $buttonTransitionEvents[$fromNodeId][$transition->event] = true;
            }

            $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                from: $fromNodeId,
                to: $toNodeId,
                kind: 'transition',
                label: $label,
                event: $transition->event,
                targetType: $transition->target->type->value,
                targetId: $transition->target->id,
                guardClass: $transition->guardClass,
                guardArgs: $transition->guardArgs,
                expectedScreen: $transition->expectedScreen,
            ));
        }
    }

    private function screenLabel(ScreenDefinition $screen): string
    {
        $text = trim($screen->text);
        if ($text === '' && $screen->textTemplate !== null) {
            $text = '{{' . $screen->textTemplate . '}}';
        }

        if ($text === '') {
            return $screen->name;
        }

        return sprintf('%s' . "\n\n" . '%s', $screen->name, $text);
    }

    /**
     * @param array<string, ScenarioFlowMapNode> $nodes
     */
    private function addNode(array &$nodes, ScenarioFlowMapNode $node): void
    {
        if (!array_key_exists($node->id, $nodes)) {
            $nodes[$node->id] = $node;
        }
    }

    /**
     * @param list<ScenarioFlowMapEdge> $edges
     * @param array<string, true> $edgeKeys
     */
    private function addEdge(array &$edges, array &$edgeKeys, ScenarioFlowMapEdge $edge): void
    {
        $key = $this->edgeIdentity($edge);
        if (array_key_exists($key, $edgeKeys)) {
            return;
        }

        $edgeKeys[$key] = true;
        $edges[]        = $edge;
    }

    /**
     * @param array<string, ScenarioFlowMapNode> $nodes
     * @return list<ScenarioFlowMapNode>
     */
    private function nodesSorted(array $nodes): array
    {
        ksort($nodes);

        return array_values($nodes);
    }

    private function edgeIdentity(ScenarioFlowMapEdge $edge): string
    {
        return $edge->from
            . '|'
            . $edge->to
            . '|'
            . $edge->kind
            . '|'
            . $edge->label
            . '|'
            . ($edge->event ?? '')
            . '|'
            . ($edge->targetType ?? '')
            . '|'
            . ($edge->targetId ?? '')
            . '|'
            . ($edge->guardClass ?? '')
            . '|'
            . ($edge->expectedScreen ?? '');
    }

    private function screenNodeId(string $screenId): string
    {
        return 'screen:' . trim($screenId);
    }

    private function buttonNodeId(string $buttonId): string
    {
        return 'button:' . trim($buttonId);
    }

    private function actionNodeId(string $actionId): string
    {
        return 'action:' . trim($actionId);
    }

    private function handlerNodeId(string $action, string $class, string $method): string
    {
        return 'handler:' . trim($action) . ':' . trim($class) . '::' . trim($method);
    }

    private function entryNodeId(string $entryName, int $index): string
    {
        return 'entry:' . trim($entryName) . ':' . $index;
    }

    private function shortClassName(string $class): string
    {
        $class = trim($class);
        if (!str_contains($class, '\\')) {
            return $class;
        }

        $pos = strrpos($class, '\\');
        if ($pos === false) {
            return basename($class);
        }

        return substr($class, $pos + 1, strlen($class));
    }
}
