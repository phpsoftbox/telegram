<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

final readonly class ScenarioFlowMap
{
    /**
     * @param list<ScenarioFlowMapNode> $nodes
     * @param list<ScenarioFlowMapEdge> $edges
     */
    public function __construct(
        public array $nodes,
        public array $edges,
    ) {
    }

    /**
     * @return array{
     *   nodes:list<array{id:string,type:string,label:string}>,
     *   edges:list<array{
     *     from:string,
     *     to:string,
     *     kind:string,
     *     label:string,
     *     event:?string,
     *     target_type:?string,
     *     target_id:?string,
     *     guard_class:?string,
     *     guard_args:array<string,mixed>,
     *     expected_screen:?string
     *   }>
     * }
     */
    public function toArray(): array
    {
        $nodes = [];
        foreach ($this->nodes as $node) {
            $nodes[] = [
                'id'    => $node->id,
                'type'  => $node->type,
                'label' => $node->label,
            ];
        }

        $edges = [];
        foreach ($this->edges as $edge) {
            $edges[] = [
                'from'            => $edge->from,
                'to'              => $edge->to,
                'kind'            => $edge->kind,
                'label'           => $edge->label,
                'event'           => $edge->event,
                'target_type'     => $edge->targetType,
                'target_id'       => $edge->targetId,
                'guard_class'     => $edge->guardClass,
                'guard_args'      => $edge->guardArgs,
                'expected_screen' => $edge->expectedScreen,
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }
}
