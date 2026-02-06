<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use function addslashes;
use function array_key_exists;
use function array_values;
use function count;
use function implode;
use function in_array;
use function str_replace;
use function strtoupper;
use function trim;

final class ScenarioFlowMapDotRenderer
{
    public function render(ScenarioFlowMap $map, string $rankdir = 'TB'): string
    {
        $rankdir   = $this->normalizeRankdir($rankdir);
        $projected = $this->projectSwitchNodes($map);

        $nodeById = [];
        foreach ($projected->nodes as $index => $node) {
            $nodeById[$node->id] = 'n' . ($index + 1);
        }

        $lines = [
            'digraph TelegramScenarioFlow {',
            '    rankdir=' . $rankdir . ';',
            '    graph [bgcolor="#ffffff", fontname="Arial", fontsize=10, labelloc="t", label="Telegram Scenario Flow Map"];',
            '    node [fontname="Arial", fontsize=10];',
            '    edge [fontname="Arial", fontsize=9, color="#6b7280"];',
        ];

        foreach ($projected->nodes as $node) {
            $token = $nodeById[$node->id] ?? null;
            if ($token === null) {
                continue;
            }

            $attributes = $this->nodeAttributes($node);
            $lines[]    = '    ' . $token . ' [' . $attributes . '];';
        }

        foreach ($projected->edges as $edge) {
            $from = $nodeById[$edge->from] ?? null;
            $to   = $nodeById[$edge->to] ?? null;
            if ($from === null || $to === null) {
                continue;
            }

            $attributes = $this->edgeAttributes($edge);
            $lines[]    = '    ' . $from . ' -> ' . $to . ' [' . $attributes . '];';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function normalizeRankdir(string $rankdir): string
    {
        $value = strtoupper(trim($rankdir));
        if (!in_array($value, ['TB', 'BT', 'LR', 'RL'], true)) {
            return 'TB';
        }

        return $value;
    }

    private function nodeAttributes(ScenarioFlowMapNode $node): string
    {
        $shape = 'box';
        $style = 'rounded,filled';
        $fill  = '#dbeafe';
        $color = '#93c5fd';

        if ($node->type === 'button') {
            $shape = 'oval';
            $style = 'filled';
            $fill  = '#dcfce7';
            $color = '#86efac';
        } elseif ($node->type === 'action') {
            $shape = 'diamond';
            $style = 'filled';
            $fill  = '#fef3c7';
            $color = '#fcd34d';
        } elseif ($node->type === 'entry') {
            $shape = 'oval';
            $style = 'filled';
            $fill  = '#fee2e2';
            $color = '#fca5a5';
        } elseif ($node->type === 'handler') {
            $shape = 'component';
            $style = 'filled';
            $fill  = '#f3f4f6';
            $color = '#d1d5db';
        } elseif ($node->type === 'switch') {
            $shape = 'diamond';
            $style = 'filled';
            $fill  = '#ede9fe';
            $color = '#a78bfa';
        } elseif ($node->type === 'guard') {
            $shape = 'diamond';
            $style = 'filled';
            $fill  = '#fef3c7';
            $color = '#f59e0b';
        } elseif ($node->type === 'exit') {
            $shape = 'octagon';
            $style = 'filled,bold';
            $fill  = '#fee2e2';
            $color = '#f87171';
        }

        return 'label="' . $this->dotLabel($node->label)
            . '", shape="' . $shape
            . '", style="' . $style
            . '", fillcolor="' . $fill
            . '", color="' . $color
            . '"';
    }

    private function edgeAttributes(ScenarioFlowMapEdge $edge): string
    {
        $color = '#6b7280';
        $style = 'solid';

        if ($edge->kind === 'screen_button') {
            $color = '#16a34a';
        } elseif ($edge->kind === 'entry') {
            $color = '#ef4444';
            $style = 'dashed';
        } elseif ($edge->kind === 'switch') {
            $color = '#7c3aed';
            $style = 'dashed';
        } elseif ($edge->kind === 'action_handler') {
            $color = '#9ca3af';
            $style = 'dashed';
        } elseif ($edge->kind === 'exit') {
            $color = '#dc2626';
            $style = 'bold';
        }

        $attributes = [
            'color="' . $color . '"',
            'style="' . $style . '"',
            'constraint="true"',
        ];

        if ($edge->label !== '') {
            $attributes[] = 'label="' . $this->dotLabel($edge->label) . '"';
        }

        return implode(', ', $attributes);
    }

    private function dotLabel(string $value): string
    {
        return addslashes(str_replace("\r", '', trim($value)));
    }

    private function projectSwitchNodes(ScenarioFlowMap $map): ScenarioFlowMap
    {
        $nodes    = [];
        $edges    = [];
        $edgeKeys = [];
        foreach ($map->nodes as $node) {
            $nodes[$node->id] = $node;
        }

        $transitionGroups = [];
        foreach ($map->edges as $index => $edge) {
            if ($edge->kind !== 'transition') {
                continue;
            }
            if ($edge->event === null || $edge->event === '') {
                continue;
            }

            $groupId                      = $edge->from . '|' . $edge->event;
            $transitionGroups[$groupId][] = $index;
        }

        foreach ($map->edges as $index => $edge) {
            if ($edge->kind !== 'transition' || $edge->event === null || $edge->event === '') {
                $this->addEdge($edges, $edgeKeys, $edge);
                continue;
            }

            $groupId      = $edge->from . '|' . $edge->event;
            $groupIndexes = $transitionGroups[$groupId] ?? [];
            if (count($groupIndexes) < 2) {
                $this->addEdge($edges, $edgeKeys, $edge);
                continue;
            }

            $switchId = 'switch:' . $edge->from . ':' . $edge->event;
            if (!array_key_exists($switchId, $nodes)) {
                $nodes[$switchId] = new ScenarioFlowMapNode(
                    id: $switchId,
                    type: 'switch',
                    label: 'switch: ' . $edge->event,
                );
            }

            $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                from: $edge->from,
                to: $switchId,
                kind: 'switch',
                label: $edge->event,
                event: $edge->event,
            ));

            $guardLabel = trim($edge->label);
            $this->addEdge($edges, $edgeKeys, new ScenarioFlowMapEdge(
                from: $switchId,
                to: $edge->to,
                kind: 'transition',
                label: $guardLabel,
                event: $edge->event,
                targetType: $edge->targetType,
                targetId: $edge->targetId,
                guardClass: $edge->guardClass,
                guardArgs: $edge->guardArgs,
                expectedScreen: $edge->expectedScreen,
            ));
        }

        return new ScenarioFlowMap(
            nodes: array_values($nodes),
            edges: $edges,
        );
    }

    /**
     * @param list<ScenarioFlowMapEdge> $edges
     * @param array<string, true> $edgeKeys
     */
    private function addEdge(array &$edges, array &$edgeKeys, ScenarioFlowMapEdge $edge): void
    {
        $key = $edge->from
            . '|'
            . $edge->to
            . '|'
            . $edge->kind
            . '|'
            . $edge->label
            . '|'
            . ($edge->event ?? '');
        if (array_key_exists($key, $edgeKeys)) {
            return;
        }

        $edgeKeys[$key] = true;
        $edges[]        = $edge;
    }
}
