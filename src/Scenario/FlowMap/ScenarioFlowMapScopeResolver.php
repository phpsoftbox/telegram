<?php

declare(strict_types=1);

namespace PhpSoftBox\Telegram\Scenario\FlowMap;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function array_values;
use function is_string;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

final class ScenarioFlowMapScopeResolver
{
    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @param list<ScenarioFlowMapCjm> $cjms
     */
    public function resolve(ScenarioFlowMap $map, string $scope = 'all', array $branches = [], array $cjms = []): ScenarioFlowMap
    {
        $scope = trim($scope);
        if ($scope === '' || $scope === 'all') {
            return $map;
        }

        $branchMap = $this->branchesMap($branches);
        $cjmMap    = $this->cjmsMap($cjms);

        if (str_starts_with($scope, 'screen:')) {
            $screenId = trim(substr($scope, strlen('screen:')));
            if ($screenId === '') {
                return $this->notFound('Screen scope is empty');
            }

            return $this->resolveScreenScope($map, $screenId);
        }

        if (str_starts_with($scope, 'cjm:')) {
            $cjmId = trim(substr($scope, strlen('cjm:')));
            if ($cjmId === '') {
                return $this->notFound('CJM scope is empty');
            }

            $cjm = $cjmMap[$cjmId] ?? null;
            if (!$cjm instanceof ScenarioFlowMapCjm) {
                return $this->notFound('CJM scope not found: ' . $cjmId);
            }

            return $this->resolveCjmScope($map, $cjm, $branchMap);
        }

        if (str_starts_with($scope, 'branch:')) {
            $branchId = trim(substr($scope, strlen('branch:')));
            if ($branchId === '') {
                return $this->notFound('Branch scope is empty');
            }

            $branch = $branchMap[$branchId] ?? null;
            if (!$branch instanceof ScenarioFlowMapBranch) {
                return $this->notFound('Branch scope not found: ' . $branchId);
            }

            return $this->resolveBranchScope($map, $branch);
        }

        $branch = $branchMap[$scope] ?? null;
        if ($branch instanceof ScenarioFlowMapBranch) {
            return $this->resolveBranchScope($map, $branch);
        }

        $cjm = $cjmMap[$scope] ?? null;
        if ($cjm instanceof ScenarioFlowMapCjm) {
            return $this->resolveCjmScope($map, $cjm, $branchMap);
        }

        return $this->resolveScreenScope($map, $scope);
    }

    /**
     * @param list<ScenarioFlowMapBranch> $branches
     * @return array<string, ScenarioFlowMapBranch>
     */
    private function branchesMap(array $branches): array
    {
        $result = [];
        foreach ($branches as $branch) {
            $result[$branch->id] = $branch;
        }

        return $result;
    }

    /**
     * @param list<ScenarioFlowMapCjm> $cjms
     * @return array<string, ScenarioFlowMapCjm>
     */
    private function cjmsMap(array $cjms): array
    {
        $result = [];
        foreach ($cjms as $cjm) {
            $result[$cjm->id] = $cjm;
        }

        return $result;
    }

    private function resolveScreenScope(ScenarioFlowMap $map, string $screenId): ScenarioFlowMap
    {
        $nodesById   = $this->nodesById($map);
        $edgesByFrom = $this->edgesByFrom($map);
        $rootNodeId  = 'screen:' . trim($screenId);

        if (!array_key_exists($rootNodeId, $nodesById)) {
            return $this->notFound('Scope screen not found: ' . $screenId);
        }

        $selectedNodes    = [$rootNodeId => true];
        $selectedEdges    = [];
        $selectedEdgeKeys = [];
        $queue            = [$rootNodeId];
        $visited          = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_string($current) || $current === '') {
                continue;
            }
            if (array_key_exists($current, $visited)) {
                continue;
            }
            $visited[$current] = true;

            foreach ($edgesByFrom[$current] ?? [] as $edge) {
                $this->appendEdge($selectedEdges, $selectedEdgeKeys, $edge);
                $selectedNodes[$edge->from] = true;
                $selectedNodes[$edge->to]   = true;

                if (!array_key_exists($edge->to, $visited)) {
                    $queue[] = $edge->to;
                }
            }
        }

        return $this->project($nodesById, $selectedNodes, $selectedEdges);
    }

    private function resolveBranchScope(ScenarioFlowMap $map, ScenarioFlowMapBranch $branch): ScenarioFlowMap
    {
        $nodesById    = $this->nodesById($map);
        $edgesByFrom  = $this->edgesByFrom($map);
        $edgesByEvent = $this->edgesByEvent($map);

        $entryNodeIds = [];
        $nodeId       = 'screen:' . trim($branch->entryScreen);
        if (array_key_exists($nodeId, $nodesById)) {
            $entryNodeIds[$nodeId] = true;
        }

        $allowedEvents = [];
        foreach ($branch->entryEvents as $event) {
            $allowedEvents[$event] = 'internal';
        }
        foreach ($branch->internalEvents as $event) {
            $allowedEvents[$event] = 'internal';
        }
        foreach ($branch->exitEvents as $event) {
            $allowedEvents[$event] = 'exit';
        }

        $exitScreens = [];
        foreach ($branch->exitScreens as $screenId) {
            $exitScreens[$screenId] = true;
        }

        $selectedNodes    = $entryNodeIds;
        $selectedEdges    = [];
        $selectedEdgeKeys = [];
        $exitNodes        = [];
        $queue            = array_keys($entryNodeIds);
        $visited          = [];

        foreach ($branch->entryEvents as $entryEvent) {
            foreach ($edgesByEvent[$entryEvent] ?? [] as $edge) {
                $this->appendEdge($selectedEdges, $selectedEdgeKeys, $edge);
                $selectedNodes[$edge->from] = true;
                $selectedNodes[$edge->to]   = true;
                if (!array_key_exists($edge->to, $visited)) {
                    $queue[] = $edge->to;
                }
            }
        }

        if ($selectedNodes === []) {
            return $this->notFound(
                sprintf('Branch scope "%s" has no reachable entry screens or entry events', $branch->id),
            );
        }

        while ($queue !== []) {
            $current = array_shift($queue);
            if (!is_string($current) || $current === '') {
                continue;
            }
            if (array_key_exists($current, $visited)) {
                continue;
            }
            $visited[$current] = true;

            foreach ($edgesByFrom[$current] ?? [] as $edge) {
                $event = trim((string) ($edge->event ?? ''));
                if ($event !== '' && !str_starts_with($event, 'entry:') && !array_key_exists($event, $allowedEvents)) {
                    continue;
                }

                $toType     = $nodesById[$edge->to]->type ?? '';
                $toScreenId = $toType === 'screen' ? $this->screenIdFromNode($edge->to) : null;
                if ($toScreenId !== null && array_key_exists($toScreenId, $exitScreens)) {
                    $exitNode                 = $this->exitNode('screen:' . $toScreenId);
                    $exitNodes[$exitNode->id] = $exitNode;

                    $selectedNodes[$current]      = true;
                    $selectedNodes[$exitNode->id] = true;

                    $this->appendEdge($selectedEdges, $selectedEdgeKeys, new ScenarioFlowMapEdge(
                        from: $current,
                        to: $exitNode->id,
                        kind: 'exit',
                        label: 'screen:' . $toScreenId,
                        event: $event !== '' ? $event : null,
                        targetType: 'screen',
                        targetId: $toScreenId,
                        expectedScreen: $toScreenId,
                    ));

                    continue;
                }

                $eventMode = $event !== '' ? ($allowedEvents[$event] ?? null) : null;
                if ($eventMode === 'exit') {
                    $exitNode                 = $this->exitNode($event);
                    $exitNodes[$exitNode->id] = $exitNode;

                    $selectedNodes[$current]      = true;
                    $selectedNodes[$exitNode->id] = true;

                    $this->appendEdge($selectedEdges, $selectedEdgeKeys, new ScenarioFlowMapEdge(
                        from: $current,
                        to: $exitNode->id,
                        kind: 'exit',
                        label: $event,
                        event: $event,
                    ));

                    continue;
                }

                $this->appendEdge($selectedEdges, $selectedEdgeKeys, $edge);
                $selectedNodes[$edge->from] = true;
                $selectedNodes[$edge->to]   = true;

                if (!array_key_exists($edge->to, $visited)) {
                    $queue[] = $edge->to;
                }
            }
        }

        // Final projection to keep hard branch boundary even if an edge slipped through.
        $projectedEdges = [];
        $projectedKeys  = [];
        foreach ($selectedEdges as $edge) {
            $toType     = $nodesById[$edge->to]->type ?? '';
            $toScreenId = $toType === 'screen' ? $this->screenIdFromNode($edge->to) : null;

            if ($toScreenId === null || !array_key_exists($toScreenId, $exitScreens)) {
                $this->appendEdge($projectedEdges, $projectedKeys, $edge);
                continue;
            }

            $exitNode                     = $this->exitNode('screen:' . $toScreenId);
            $exitNodes[$exitNode->id]     = $exitNode;
            $selectedNodes[$edge->from]   = true;
            $selectedNodes[$exitNode->id] = true;

            $this->appendEdge($projectedEdges, $projectedKeys, new ScenarioFlowMapEdge(
                from: $edge->from,
                to: $exitNode->id,
                kind: 'exit',
                label: 'screen:' . $toScreenId,
                event: $edge->event,
                targetType: 'screen',
                targetId: $toScreenId,
                expectedScreen: $toScreenId,
            ));
        }

        foreach ($exitNodes as $exitNode) {
            $nodesById[$exitNode->id] = $exitNode;
        }

        return $this->project($nodesById, $selectedNodes, $projectedEdges);
    }

    /**
     * @param array<string, ScenarioFlowMapBranch> $branchMap
     */
    private function resolveCjmScope(ScenarioFlowMap $map, ScenarioFlowMapCjm $cjm, array $branchMap): ScenarioFlowMap
    {
        $nodes         = [];
        $edges         = [];
        $edgeKeys      = [];
        $foundBranches = 0;

        foreach ($cjm->branches as $branchId) {
            $branch = $branchMap[$branchId] ?? null;
            if (!$branch instanceof ScenarioFlowMapBranch) {
                continue;
            }

            $branchMapResult = $this->resolveBranchScope($map, $branch);
            if ($this->isNotFoundScopeMap($branchMapResult)) {
                continue;
            }

            $foundBranches++;
            foreach ($branchMapResult->nodes as $node) {
                $nodes[$node->id] = $node;
            }
            foreach ($branchMapResult->edges as $edge) {
                $this->appendEdge($edges, $edgeKeys, $edge);
            }
        }

        if ($foundBranches === 0) {
            return $this->notFound(
                sprintf('CJM scope "%s" has no reachable branches', $cjm->id),
            );
        }

        return new ScenarioFlowMap(
            nodes: array_values($nodes),
            edges: $edges,
        );
    }

    /**
     * @param array<string, ScenarioFlowMapNode> $nodesById
     * @param array<string, bool> $selectedNodeIds
     * @param list<ScenarioFlowMapEdge> $edges
     */
    private function project(array $nodesById, array $selectedNodeIds, array $edges): ScenarioFlowMap
    {
        $usedNodeIds = [];
        foreach ($edges as $edge) {
            $usedNodeIds[$edge->from] = true;
            $usedNodeIds[$edge->to]   = true;
        }

        $nodes = [];
        foreach ($nodesById as $nodeId => $node) {
            if (!array_key_exists($nodeId, $selectedNodeIds)) {
                continue;
            }
            if (!array_key_exists($nodeId, $usedNodeIds)) {
                continue;
            }
            $nodes[] = $node;
        }

        return new ScenarioFlowMap(
            nodes: $nodes,
            edges: $edges,
        );
    }

    private function exitNode(string $token): ScenarioFlowMapNode
    {
        $token = trim($token);

        return new ScenarioFlowMapNode(
            id: 'exit:' . $token,
            type: 'exit',
            label: 'EXIT: ' . $token,
        );
    }

    private function screenIdFromNode(string $nodeId): ?string
    {
        if (!str_starts_with($nodeId, 'screen:')) {
            return null;
        }

        $screenId = trim(substr($nodeId, strlen('screen:')));
        if ($screenId === '') {
            return null;
        }

        return $screenId;
    }

    private function notFound(string $message): ScenarioFlowMap
    {
        return new ScenarioFlowMap(
            nodes: [
                new ScenarioFlowMapNode(
                    id: 'screen:not_found',
                    type: 'screen',
                    label: trim($message),
                ),
            ],
            edges: [],
        );
    }

    private function isNotFoundScopeMap(ScenarioFlowMap $map): bool
    {
        if ($map->edges !== []) {
            return false;
        }
        if ($map->nodes === [] || !isset($map->nodes[0])) {
            return false;
        }

        return $map->nodes[0]->id === 'screen:not_found';
    }

    /**
     * @param list<ScenarioFlowMapEdge> $edges
     * @param array<string, true> $edgeKeys
     */
    private function appendEdge(array &$edges, array &$edgeKeys, ScenarioFlowMapEdge $edge): void
    {
        $key = $this->edgeIdentity($edge);
        if (array_key_exists($key, $edgeKeys)) {
            return;
        }

        $edgeKeys[$key] = true;
        $edges[]        = $edge;
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
            . ($edge->event ?? '');
    }

    /**
     * @return array<string, ScenarioFlowMapNode>
     */
    private function nodesById(ScenarioFlowMap $map): array
    {
        $nodes = [];
        foreach ($map->nodes as $node) {
            $nodes[$node->id] = $node;
        }

        return $nodes;
    }

    /**
     * @return array<string, list<ScenarioFlowMapEdge>>
     */
    private function edgesByFrom(ScenarioFlowMap $map): array
    {
        $edgesByFrom = [];
        foreach ($map->edges as $edge) {
            $edgesByFrom[$edge->from][] = $edge;
        }

        return $edgesByFrom;
    }

    /**
     * @return array<string, list<ScenarioFlowMapEdge>>
     */
    private function edgesByEvent(ScenarioFlowMap $map): array
    {
        $edgesByEvent = [];
        foreach ($map->edges as $edge) {
            $event = trim((string) ($edge->event ?? ''));
            if ($event === '') {
                continue;
            }
            $edgesByEvent[$event][] = $edge;
        }

        return $edgesByEvent;
    }
}
